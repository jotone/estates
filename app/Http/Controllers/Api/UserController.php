<?php

namespace App\Http\Controllers\Api;

use App\Classes\FileHelper;
use App\Http\Controllers\BasicApiController;
use App\Http\Requests\Api\UserStoreRequest;
use App\Models\User;
use App\Rules\AlreadyExists;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\{Auth, DB, Validator};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Validation\Rules\{File, Password};

class UserController extends BasicApiController
{
    /**
     * User list
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        return $this->indexRequest(
            $request,
            User::select(['users.*'])
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id'),
            function ($content, $search) {

                return $content->whereRaw("LOWER(name) LIKE '%$search%' OR LOWER(email) LIKE '%$search%'");
            }
        );
    }

    /**
     * User Data
     *
     * @param int $user_id
     * @param Request $request
     * @return JsonResponse
     */
    public function show(int $user_id, Request $request): JsonResponse
    {
        return $this->showRequest($user_id, User::query(), $request);
    }

    /**
     * Store user on the database
     * @param UserStoreRequest $request
     * @return JsonResponse
     */
    public function store(UserStoreRequest $request): JsonResponse
    {
        DB::beginTransaction();
        // Create user
        $user = User::create($request->validated());

        // Check img_url file exists
        if ($request->hasFile('img_url')) {
            try {
                // Attempt to save file
                $user->img_url = FileHelper::saveFile($request->file('img_url'), 'storage/users/' . $user->id, 'user_img');
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['errors' => ['img_url' => [$e->getMessage()]]], 400);
            }
        }

        $user->save();
        DB::commit();

        $token = $user->createToken('user_access_token');

        event(new Registered($user));

        return response()->json(
            array_merge(
                $user->toArray(),
                ['token' => $token->plainTextToken]
            ), 201
        );
    }

    /**
     * Update user
     *
     * @param User $user
     * @param Request $request
     * @return JsonResponse
     */
    public function update(User $user, Request $request): JsonResponse
    {
        $args = $request->only([
            'name',
            'email',
            'img_url',
            'password',
            'confirmation',
            'roles',
            'phone',
            'town_id',
            'about',
            'lang'
        ]);

        if ((Auth::user()?->role?->level ?? 255) > ($user?->role?->level ?? 255)) {
            return response()->json(['errors' => ['role_id' => [__('roles.errors.permissions')]]], 403);
        }

        $rules = [];

        foreach ($args as $key => $val) {
            switch ($key) {
                case 'about':
                    $rules[$key] = ['nullable', 'string'];
                    $user->$key = $val;
                    break;
                case 'email':
                    $rules[$key] = ['required', 'email', new AlreadyExists('users', $user->id)];
                    $user->$key = $val;
                    break;
                case 'img_url':
                    $rules[$key] = ['nullable', File::types(['jpg', 'jpeg', 'png'])];
                    break;
                case 'lang':
                    $rules[$key] = ['required', 'string', 'max:2'];
                    $user->$key = $val;
                    break;
                case 'name':
                    $rules[$key] = ['required', 'string'];
                    $user->$key = $val;
                    break;
                case 'password':
                    $rules[$key] = ['nullable', Password::defaults()];
                    $rules['confirmation'] = ['nullable', 'string', 'same:password'];
                    if (!empty($val)) {
                        $user->$key = $val;
                    }
                    break;
                case 'phone':
                    $rules[$key] = ['nullable', 'string', 'max:64'];
                    $user->$key = $val;
                    break;
                case 'roles':
                    $rules[$key] = ['nullable', 'array'];
                    $rules[$key . '.*'] = ['exists:roles,id'];
                    $user->$key = $val;
                    break;
                case 'town_id':
                    break;
            }
        }

        $validation = Validator::make($args, $rules);

        if ($validation->fails()) {
            return response()->json(['errors' => $validation->errors()], 422);
        }

        // Check img_url file exists
        if ($request->hasFile('img_url')) {
            try {
                // Remove previous user image
                if (!is_null($user->img_url)) {
                    FileHelper::removeFile($user->img_url);
                }

                // Attempt to save file
                $user->img_url = FileHelper::saveFile($request->file('img_url'), 'storage/users/' . $user->id);
            } catch (\Exception $e) {
                return response()->json(['errors' => ['img_url' => [$e->getMessage()]]], 400);
            }
        }
        // Check the image was cleared
        if (array_key_exists('img_url', $args) && is_null($args['img_url'])) {
            // Remove user image
            FileHelper::removeFile($user->img_url);
            $user->img_url = null;
        }

        $user->save();

        return response()->json($user);
    }

    /**
     * Remove user
     *
     * @param User $user
     * @return JsonResponse
     * @throws \Exception
     */
    public function destroy(User $user): JsonResponse
    {
        return $this->destroyRequest($user);
    }
}