<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Registered;
use Laravel\Socialite\Facades\Socialite;

use App\Models\User;
use App\Models\Project;
use App\Models\Directory;
use App\Models\Route;
use App\Models\Setting;
use App\Models\Permission;
use App\Models\Statistic;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/?edit';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('guest')->except('logout');
    }

    /**
     * Obtain the user information from Google.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleProviderCallback(Request $request) {
        //check csrf
        try {
            $client = new \Google_Client(['client_id' => '748673829350-6hqja940saq5tv883oheqbpkkdlorvvg.apps.googleusercontent.com']);
            $payload = $client->verifyIdToken($request->credential);
            if ($payload) {
                $userid = $payload['sub'];
                // If request specified a G Suite domain:
                //$domain = $payload['hd'];
                $existingUser = User::where('email', $payload['email'])->first();
                if (!empty($existingUser)){
                    // log them in
                    auth()->login($existingUser, true);
                } else {
                    // create a new user
                    $curTime = new \DateTime();
                    $newUser = new User;
                    $newUser->name = $payload['name'];
                    $newUser->email = $payload['email'];
                    $newUser->picture = $payload['picture'];
                    $newUser->google_id = $userid;
                    $newUser->password = NULL;
                    $newUser->email_verified_at = $curTime->format("Y-m-d H:i:s");
                    $newUser->save();
                    auth()->login($newUser, true);
                }
            }
        } catch (\Throwable $e) {
            return redirect('/register')->withErrors(['email', $e]);
        }
        return redirect('/');
    }

    /**
     * Redirect the user to the Facebook authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToFacebook() {
        return Socialite::with('facebook')->stateless()->redirect();
    }

    public function handleFacebookCallback() {
        $facebookUser = Socialite::driver('facebook')->stateless()->user();
        $user = User::where('facebook_id', $facebookUser->getId())->first();
        if (empty($user)) {
            $user = new User;
            $user->name = $facebookUser->name;
            if (!empty($facebookUser->email)) {
                $user->email = $facebookUser->email;
            }
            $user->uuid = \Str::uuid()->toString();
            $user->facebook_id = $facebookUser->id;
            $user->save();
            Auth::loginUsingId($user->id);
        } else {
            Auth::loginUsingId($user->id);
        }
        return $this->redirectTo('oAuth');
    }

    public function redirectToGithub() {
        return Socialite::with('github')->stateless()->redirect();
    }

    public function handleGithubCallback() {
        $githubUser = Socialite::driver('github')->stateless()->user();
        $user = User::where('github_id', $githubUser->getId())->first();
        if (empty($user)) {
            $user = new User;
            $user->name = $githubUser->name;
            $user->uuid = \Str::uuid()->toString();
            $user->email = $githubUser->email;
            $user->github_id = $githubUser->id;
            $user->save();
            Auth::loginUsingId($user->id);
        } else {
            Auth::loginUsingId($user->id);
        }
        return $this->redirectTo('oAuth');
    }

    public function redirectToGoogle() {
        return Socialite::with('google')->stateless()->redirect();
    }

    public function handleGoogleCallback(Request $request) {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $user = User::where('google_id', $googleUser->getId())->first();
        $existingEmail = User::where('email', $googleUser->email)->first();
        if (empty($user) && empty($existingEmail)) {
            $userData = [
                'name' => $googleUser->name,
                'email' => $googleUser->email,
                'google_id' => $googleUser->id
            ];
    
            $userId = \Str::uuid()->toString();
            $projectId = \Str::uuid()->toString();
            $curTime = new \DateTime();
            $userData['email_verified_at'] = $curTime->format("Y-m-d H:i:s");
            $userData['uuid'] = $userId;
            $userData['project_id'] = $projectId;
    
            $user = User::create($userData);
    
            //Create directories
            $migrationDirectoryId = \Str::uuid()->toString();
            $migrationDirectoryData = [];
            $migrationDirectoryData['uuid'] = $migrationDirectoryId;
            $migrationDirectoryData['type'] = 'migration';
            $migrationDirectoryData['name'] = 'migrations';
            $migrationDirectoryData['data'] = [];
            $migrationDirectory = Directory::create([
                'user_id' => $user->id,
                'project_id' => $projectId,
                'uuid' => $migrationDirectoryId,
                'name' => 'migrations',
                'type' => 'migration',
                'data' => json_encode($migrationDirectoryData)
            ]);

            $jsDirectoryId = \Str::uuid()->toString();
            $jsDirectoryData = [];
            $jsDirectoryData['uuid'] = $jsDirectoryId;
            $jsDirectoryData['type'] = 'js';
            $jsDirectoryData['name'] = 'js';
            $jsDirectoryData['data'] = [];
            $jsDirectory = Directory::create([
                'user_id' => $user->id,
                'project_id' => $projectId,
                'uuid' => $jsDirectoryId,
                'name' => 'js',
                'type' => 'js',
                'data' => json_encode($jsDirectoryData)
            ]);

            $modelsDirectoryId = \Str::uuid()->toString();
            $modelsDirectoryData = [];
            $modelsDirectoryData['uuid'] = $modelsDirectoryId;
            $modelsDirectoryData['type'] = 'model';
            $modelsDirectoryData['name'] = 'models';
            $modelsDirectoryData['data'] = [];
            $modelsDirectory = Directory::create([
                'user_id' => $user->id,
                'project_id' => $projectId,
                'uuid' => $modelsDirectoryId,
                'name' => 'models',
                'type' => 'model',
                'data' => json_encode($modelsDirectoryData)
            ]);

            $controllersDirectoryId = \Str::uuid()->toString();
            $controllersDirectoryData = [];
            $controllersDirectoryData['uuid'] = $controllersDirectoryId;
            $controllersDirectoryData['type'] = 'controller';
            $controllersDirectoryData['name'] = 'controllers';
            $controllersDirectoryData['data'] = [];
            $controllersDirectory = Directory::create([
                'user_id' => $user->id,
                'project_id' => $projectId,
                'uuid' => $controllersDirectoryId,
                'name' => 'controllers',
                'type' => 'controller',
                'data' => json_encode($controllersDirectoryData)
            ]);

            $seedersDirectoryId = \Str::uuid()->toString();
            $seedersDirectoryData = [];
            $seedersDirectoryData['uuid'] = $seedersDirectoryId;
            $seedersDirectoryData['type'] = 'seeder';
            $seedersDirectoryData['name'] = 'seeders';
            $seedersDirectoryData['data'] = [];
            $seedersDirectoryData = Directory::create([
                'user_id' => $user->id,
                'project_id' => $projectId,
                'uuid' => $seedersDirectoryId,
                'name' => 'seeders',
                'type' => 'seeder',
                'data' => json_encode($seedersDirectoryData)
            ]);

            $factoriesDirectoryId = \Str::uuid()->toString();
            $factoriesDirectoryData = [];
            $factoriesDirectoryData['uuid'] = $factoriesDirectoryId;
            $factoriesDirectoryData['type'] = 'factory';
            $factoriesDirectoryData['name'] = 'factories';
            $factoriesDirectoryData['data'] = [];
            $factoriesDirectoryData = Directory::create([
                'user_id' => $user->id,
                'project_id' => $projectId,
                'uuid' => $factoriesDirectoryId,
                'name' => 'factories',
                'type' => 'factory',
                'data' => json_encode($factoriesDirectoryData)
            ]);

            $testsDirectoryId = \Str::uuid()->toString();
            $testsDirectoryData = [];
            $testsDirectoryData['uuid'] = $testsDirectoryId;
            $testsDirectoryData['type'] = 'test';
            $testsDirectoryData['name'] = 'tests';
            $testsDirectoryData['data'] = [];
            $testsDirectoryData = Directory::create([
                'user_id' => $user->id,
                'project_id' => $projectId,
                'uuid' => $testsDirectoryId,
                'name' => 'tests',
                'type' => 'test',
                'data' => json_encode($testsDirectoryData)
            ]);

            //Create new project
            $projectData = [];
            $projectData['uuid'] = $projectId;
            $projectData['name'] = 'New project';
            $projectData['branch'] = 'main';
            $projectData['branches'] = ['main'];
            $projectData['data'] = [$migrationDirectoryId, $jsDirectoryId, $modelsDirectoryId, $controllersDirectoryId, $seedersDirectoryId, $factoriesDirectoryId, $testsDirectoryId];
            $project = Project::create([
                'uuid' => $projectId,
                'user_id' => $user->id,
                'name' => 'New Project',
                'data' => json_encode($projectData)
            ]);

            //Set user permissions
            $permissions = Permission::create([
                'uuid' => $userId,
                'project_id' => $projectId,
                'super' => true,
                'read' => true,
                'write' => true,
                'execute' => true
            ]);

            //Create app config
            $configData = [];
            $configData['meta_title'] = 'New Project';

            $settings = Setting::create([
                'project_id' => $projectId,
                'name' => 'app',
                'active_domain' => $request->root(),
                'data' => json_encode($configData)
            ]);

            $dbSettings = Setting::create([
                'project_id' => $projectId,
                'name' => 'database',
                'active_domain' => $request->root(),
                'data' => json_encode([])
            ]);

            //Create first page
            $pageId = \Str::uuid()->toString();
            $pageData = [];
            $pageData['uuid'] = $pageId;
            $pageData['path'] = '/';
            $pageData['type'] = 'webpage';
            $pageData['method'] = 'GET';
            $pageData['name'] = 'Homepage';
            $pageData['data'] = [];
            $page = Route::create([
                'uuid' => $pageId,
                'user_id' => $user->id,
                'project_id' => $projectId,
                'name' => 'Homepage',
                'path' => '/',
                'method' => 'GET',
                'type' => 'webpage',
                'data' => json_encode($pageData)
            ]);

            //Log statistics
            $statistic = Statistic::create([
                'user_id' => $user->id,
                'projects' => 1,
                'routes' => 1,
                'elements' => 0,
                'definitions' => 0,
                'files' => 0,
                'methods' => 0,
                'statements' => 0,
                'clauses' => 0,
                'users' => 0,
                'metas' => 0
            ]);
            
            $user->createAsStripeCustomer();
    
            event(new Registered($user));
    
            Auth::login($user);
    
        } else {
            if (!empty($user)) {
                Auth::loginUsingId($user->id);
            } else if (!empty($existingEmail)) {
                Auth::loginUsingId($existingEmail->id);
            }
        }
        return redirect('/?edit');
    }

    /**
     * Custom redirect
     *
     * @return string or redirct
     */
     public function redirectTo($origin = null) {
        if ($origin == 'oAuth') {
            return redirect(config('app.home', '/?edit'));
        } else {
            return config('app.home', '/?edit');
        }
    }

    /**
     * Show the application's login form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showLoginForm()
    {
        return view('auth.login'); // or return view('stellify.login') for your custom path
    }

    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);

        if ($this->attemptLogin($request)) {
            return $this->sendLoginResponse($request);
        }

        return $this->sendFailedLoginResponse($request);
    }

    /**
     * Validate the user login request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function validateLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);
    }

    /**
     * Attempt to log the user into the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function attemptLogin(Request $request)
    {
        return Auth::attempt($request->only('email', 'password'), $request->filled('remember'));
    }

    /**
     * Send the response after the user was authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    protected function sendLoginResponse(Request $request)
    {
        $request->session()->regenerate();
        return redirect()->intended($this->redirectTo);
    }

    /**
     * Send the response after a failed login attempt.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        return redirect()->back()->withInput($request->only('email', 'remember'))
                            ->withErrors(['email' => 'These credentials do not match our records.']);
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
