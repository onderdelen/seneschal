<?php
/**
 * Created by anonymous on 17/12/15 16:34.
 */

namespace Onderdelen\JwtAuth\Controllers;

use Vinkla\Hashids\HashidsManager;
use Onderdelen\JwtAuth\FormRequests\RegisterRequest;
use Onderdelen\JwtAuth\Repositories\Group\GroupRepositoryInterface;
use Onderdelen\JwtAuth\Repositories\User\UserRepositoryInterface;
use Anwendungen\Application\Controller\Controller;
//use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Onderdelen\JwtAuth\Models\User;
use Sentinel\DataTransferObjects\BaseResponse;
use Hash;
use JWTAuth;
use Sentry;
use View;
use Input;
use Event;
use Redirect;
use Session;
use Config;

class RegistrationController extends Controller
{
    /**
     * @param UserRepositoryInterface $userRepository
     * @param GroupRepositoryInterface $groupRepository
     * @param HashidsManager $hashids
     */
    public function __construct(
        UserRepositoryInterface $userRepository,
        GroupRepositoryInterface $groupRepository,
        HashidsManager $hashids
    ) {
        $this->userRepository  = $userRepository;
        $this->groupRepository = $groupRepository;
        $this->hashids         = $hashids;

        $this->middleware('jwt.auth', ['except' => ['register']]);
    }

    /**
     * @param RegisterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        // Gather input and input data validation
        $data = $request->all();

        // Attempt Registration
        $result = $this->userRepository->store($data);

        $credentials = $request->only('email', 'password');
        try {
            // verify the credentials and create a token for the user
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json(['error' => 'invalid_credentials'], 401);
            }
        } catch (JWTException $e) {
            // something went wrong
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        // if no errors are encountered we can return a JWT
        return response()->json(compact('token'));
    }
}