<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Project;
use App\Models\Directory;
use App\Models\Route;
use App\Models\Setting;
use App\Models\Permission;
use App\Models\Statistic;

use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

use App\Traits\DatabaseConnectionTester;

class RegisteredUserController extends Controller
{
    use DatabaseConnectionTester;
    
    private $databaseConnection;

    public function __construct(Request $request) {
        $this->databaseConnection = $request->root() == 'https://stellisoft.com' ? 'pgsql' : 'mysql';
    }

    /**
     * Display the registration view if not authenticated, otherwise route to the app controller.
     *
     * @return View
     */
    public function create(Request $request)
    {
        
        if (auth()->check()) {
            return app(\App\Http\Controllers\AppController::class)->index($request);
        }
        
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request if not authenticated, otherwise route to the app controller.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        if ($request->input('stellify')) {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email:dns|max:255|unique:users',
                'password' => ['required', 'confirmed', 'max:100', Rules\Password::defaults()],
                'email_updates' => 'nullable|boolean',
                'honeypot' => 'max:0'
            ]);

            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_updates' => $request->email_updates
            ];

            // Additional spam checks
            $this->performSpamChecks($request);

            $userId = \Str::uuid()->toString();
            $projectId = \Str::uuid()->toString();

            $userData['uuid'] = $userId;
            $userData['project_id'] = $projectId;

            $user = User::create($userData);

            \DB::transaction(function () use ($user, $userId, $projectId, $request) {
                //Create directories
                $migrationDirectoryId = \Str::uuid()->toString();
                $migrationDirectoryData = [];
                $migrationDirectoryData['uuid'] = $migrationDirectoryId;
                $migrationDirectoryData['type'] = 'migration';
                $migrationDirectoryData['name'] = 'migrations';
                $migrationDirectoryData['data'] = [];
                $migrationDirectory = Directory::on($this->databaseConnection)->create([
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
                $jsDirectory = Directory::on($this->databaseConnection)->create([
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
                $modelsDirectory = Directory::on($this->databaseConnection)->create([
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
                $controllersDirectory = Directory::on($this->databaseConnection)->create([
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
                $seedersDirectoryData = Directory::on($this->databaseConnection)->create([
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
                $factoriesDirectoryData = Directory::on($this->databaseConnection)->create([
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
                $testsDirectoryData = Directory::on($this->databaseConnection)->create([
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
                $project = Project::on($this->databaseConnection)->create([
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

                $settings = Setting::on($this->databaseConnection)->create([
                    'project_id' => $projectId,
                    'name' => 'app',
                    'active_domain' => $request->root(),
                    'data' => json_encode($configData)
                ]);

                $dbSettings = Setting::on($this->databaseConnection)->create([
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
                $page = Route::on($this->databaseConnection)->create([
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
            });
            
            $user->createAsStripeCustomer();

            event(new Registered($user));

            Auth::login($user);

            return redirect(config('app.home', '/?edit'));
        } else if (auth()->check()) {
                $appController = app(\App\Http\Controllers\AppController::class);
                // Ensure any required setup is done
                return $appController->index($request);
        } else {
            return redirect('/register')->withErrors(['stellify' => 'You must agree to the terms to register.'])->withInput();
        }
    }

    /**
     * Perform additional spam detection checks
     */
    private function performSpamChecks(Request $request)
    {
        // Check for suspicious username patterns
        $name = $request->input('name');

        if ($this->isSpamName($name)) {
            throw ValidationException::withMessages([
                'name' => 'Please enter a valid name.'
            ]);
        }

        // Check for disposable email domains
        $disposableDomains = [
            '10minutemail.com', 'tempmail.org', 'guerrillamail.com', 
            'mailinator.com', 'yopmail.com', 'temp-mail.org'
        ];
        
        $emailDomain = substr(strrchr($request->email, "@"), 1);
        if (in_array(strtolower($emailDomain), $disposableDomains)) {
            throw ValidationException::withMessages([
                'email' => 'Please use a permanent email address.'
            ]);
        }
    }

    /**
     * Check if name looks like spam
     */
    private function isSpamName($name)
    {
        $cleanName = trim($name);
        
        // Too short or too long
        if (strlen($cleanName) < 2 || strlen($cleanName) > 50) {
            return true;
        }
        
        // Only letters, no spaces, and longer than 6 chars - very suspicious
        if (strlen($cleanName) > 6 && ctype_alpha($cleanName) && !preg_match('/\s/', $cleanName)) {
            // Check if it has a reasonable vowel pattern
            $vowelCount = preg_match_all('/[aeiouAEIOU]/', $cleanName);
            $totalLength = strlen($cleanName);
            
            // Real names usually have vowels making up 25-50% of the name
            $vowelRatio = $vowelCount / $totalLength;
            
            if ($vowelRatio < 0.2 || $vowelRatio > 0.7) {
                return true;
            }
            
            // Check for random case mixing (like xAUjziWkn)
            $upperCount = preg_match_all('/[A-Z]/', $cleanName);
            $lowerCount = preg_match_all('/[a-z]/', $cleanName);
            
            // If it has mixed case but isn't title case, it's suspicious
            if ($upperCount > 0 && $lowerCount > 0) {
                // Normal pattern: First letter caps, rest lowercase (John, Mary)
                // Allow: all lowercase (john) or all uppercase (JOHN)
                $isNormalCase = (
                    ctype_upper($cleanName[0]) && ctype_lower(substr($cleanName, 1)) || // Title case
                    ctype_lower($cleanName) || // All lowercase  
                    ctype_upper($cleanName)    // All uppercase
                );
                
                if (!$isNormalCase) {
                    return true;
                }
            }
        }
        
        // Flag strings that look like random character generation
        // Pattern: random consonant/vowel clusters
        if (preg_match('/[bcdfghjklmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ]{4,}/', $cleanName)) {
            return true;
        }
        
        return false;
    }
}
