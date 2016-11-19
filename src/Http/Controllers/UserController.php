<?php
/**
 * UserController.php
 * Created by @anonymoussc on 28/12/15 16:00.
 */

namespace Componeint\Seneschal\Controllers;

use Componeint\AppFoundation\Controller\Controller;
use Illuminate\Pagination\Paginator;
use Componeint\Seneschal\FormRequests\ChangePasswordRequest;
use Componeint\Seneschal\FormRequests\UserCreateRequest;
use Componeint\Seneschal\FormRequests\UserUpdateRequest;
use Componeint\Seneschal\Repositories\Group\GroupRepositoryInterface;
use Componeint\Seneschal\Repositories\User\UserRepositoryInterface;
use Componeint\Seneschal\Traits\SeneschalRedirectionTrait;
use Componeint\Seneschal\Traits\SeneschalViewfinderTrait;
use Vinkla\Hashids\HashidsManager;
use DB;
use View;
use Input;
use Event;
use Redirect;
use Session;
use Config;

class UserController extends Controller
{
    use SeneschalRedirectionTrait;
    use SeneschalViewfinderTrait;

    /**
     * Constructor
     */
    public function __construct(
        UserRepositoryInterface $userRepository,
        GroupRepositoryInterface $groupRepository,
        HashidsManager $hashids
    ) {
        $this->userRepository  = $userRepository;
        $this->groupRepository = $groupRepository;
        $this->hashids         = $hashids;

        // You must have admin access to proceed
        // $this->middleware('sentry.admin');

        // $this->middleware('jwt.auth', ['except' => ['index', 'show']]);
    }

    /**
     * Display a paginated index of all current users, with throttle data
     *
     * @return View
     */
    public function index()
    {
        // Paginate the existing users
        // $users       = $this->userRepository->all();
        // $perPage     = 15;
        // $currentPage = Input::get('page') - 1;
        // $pagedData   = array_slice($users, $currentPage * $perPage, $perPage);
        // $users       = new Paginator($pagedData, $perPage, $currentPage);

        // return $this->viewFinder('Seneschal::users.index', ['users' => $users]);

        $users = $this->userRepository->all();

        return response()->json(['count' => count($users), 'data' => $users]);
    }


    /**
     * Show the "Create new User" form
     *
     * @return View
     */
    public function create()
    {
        return $this->viewFinder('Seneschal::users.create');
    }

    /**
     * Create a new user account manually
     *
     * @return Redirect
     */
    public function store(UserCreateRequest $request)
    {
        // Create and store the new user
        $result = $this->userRepository->store($request->all());

        // Determine response message based on whether or not the user was activated
        $message = ($result->getPayload()['activated'] ? trans('Seneschal::users.addedactive') : trans('Seneschal::users.added'));

        // Finished!
        // return $this->redirectTo('users_store', ['success' => $message]);
        return response()->success(['message' => $message]);
    }


    /**
     * Show the profile of a specific user account
     *
     * @param $id
     *
     * @return View
     */
    public function show($id)
    {
        // Get the user
        $user = $this->userRepository->retrieveById($id);

        // return $this->viewFinder('Seneschal::users.show', ['user' => $user]);
        return response()->success([$user]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  $id
     *
     * @return Redirect
     */
    public function edit($id)
    {
        // Get the user
        $user = $this->userRepository->retrieveById($id);

        // Get all available groups
        $groups = $this->groupRepository->all();

        $permissions = DB::table('groups')
            ->join('users_groups', 'groups.id', '=', 'users_groups.group_id')
            ->join('users', 'users.id', '=', 'users_groups.user_id')
            ->select('groups.name')
            ->where('users.id', $user['id'])
            ->get();

        $result = [
            'user'        => $user,
            'groups'      => $groups,
            'permissions' => $permissions,
        ];

        /*
        return $this->viewFinder('Seneschal::users.edit', [
            'user'   => $user,
            'groups' => $groups,
        ]);
        */

        return response()->success([$result]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UserUpdateRequest $request
     *
     * @return Redirect
     */
    public function update(UserUpdateRequest $request)
    {
        // Gather Input
        $data = $request->all();

        // Attempt to update the user
        $result = $this->userRepository->update($data);

        // Done!
        // return $this->redirectViaResponse('users_update', $result);

        return response()->success([$result]);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  $id
     *
     * @return Redirect
     */
    public function destroy($id)
    {
        // Remove the user from storage
        $result = $this->userRepository->destroy($id);

        // return $this->redirectViaResponse('users_destroy', $result);
        return response()->success([$result]);
    }

    /**
     * Change the group memberships for a given user
     *
     * @param
     *
     * @return Redirect
     */
    public function updateGroupMemberships()
    {
        // Gather input
        $data = Input::All();

        // Change memberships
        $result = $this->userRepository->changeGroupMemberships($data['id'], $data['groups']);

        // Done
        // return $this->redirectViaResponse('users_change_memberships', $result);
        return response()->success([$result]);
    }

    /**
     * Process a password change request
     *
     * @param  $id
     *
     * @return redirect
     */
    public function changePassword(ChangePasswordRequest $request, $id)
    {
        // Gather input
        $data = Input::all();
        // $data['id'] = $this->hashids->decode($id)[0];

        // Grab the current user
        $user = $this->userRepository->getUser();

        // Change the User's password
        $result = ($user->hasAccess('admin') ? $this->userRepository->changePasswordWithoutCheck($data) : $this->userRepository->changePassword($data));

        // Was the change successful?
        if (!$result->isSuccessful()) {
            Session::flash('error', $result->getMessage());

            return Redirect::back();
        }

        return $this->redirectViaResponse('users_change_password', $result);
    }

    /**
     * Process a suspend user request
     *
     * @param  $id
     *
     * @return Redirect
     */
    public function suspend($id)
    {
        // Trigger the suspension
        $result = $this->userRepository->suspend($id);

        // return $this->redirectViaResponse('users_suspend', $result);
        return response()->success([$result]);
    }

    /**
     * Unsuspend user
     *
     * @param  $id
     *
     * @return Redirect
     */
    public function unsuspend($id)
    {
        // Trigger the unsuspension
        $result = $this->userRepository->unsuspend($id);

        // return $this->redirectViaResponse('users_unsuspend', $result);
        return response()->success([$result]);
    }

    /**
     * Ban a user
     *
     * @param  $id
     *
     * @return Redirect
     */
    public function ban($id)
    {
        // Ban the user
        $result = $this->userRepository->ban($id);

        // return $this->redirectViaResponse('users_ban', $result);
        return response()->success([$result]);
    }

    /**
     * Unban a user
     *
     * @param $id
     *
     * @return Redirect
     */
    public function unban($id)
    {
        // Unban the user
        $result = $this->userRepository->unban($id);

        // return $this->redirectViaResponse('users_unban', $result);
        return response()->success([$result]);
    }
}
