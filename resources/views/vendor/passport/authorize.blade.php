<!DOCTYPE html>
<html lang="en" class="dark h-full">
<head class="h-full">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name') }} - Authorization</title>

    <!-- Scripts -->
    @routes
    @vite('resources/js/app.js')
</head>
<body class="bg-slate-200 dark:bg-slate-900 h-full flex items-center justify-center">
    <div class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <svg class="mx-auto w-24 h-24" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_2_9)"><path fill-rule="evenodd" clip-rule="evenodd" d="M3 9V7C3 5.67392 3.52678 4.40215 4.46447 3.46447C5.40215 2.52678 6.67392 2 8 2C9.32608 2 10.5979 2.52678 11.5355 3.46447C12.4732 4.40215 13 5.67392 13 7V9C13.5304 9 14.0391 9.21071 14.4142 9.58579C14.7893 9.96086 15 10.4696 15 11V16C15 16.5304 14.7893 17.0391 14.4142 17.4142C14.0391 17.7893 13.5304 18 13 18H3C2.46957 18 1.96086 17.7893 1.58579 17.4142C1.21071 17.0391 1 16.5304 1 16V11C1 10.4696 1.21071 9.96086 1.58579 9.58579C1.96086 9.21071 2.46957 9 3 9ZM11 7V9H5V7C5 6.20435 5.31607 5.44129 5.87868 4.87868C6.44129 4.31607 7.20435 4 8 4C8.79565 4 9.55871 4.31607 10.1213 4.87868C10.6839 5.44129 11 6.20435 11 7Z" fill="#AEACA9"/><path fill-rule="evenodd" clip-rule="evenodd" d="M7.91893 0.792063C5.2956 1.26991 3.85496 2.66179 3.59739 4.96765C4.3822 3.69522 5.45534 3.07628 6.81637 3.11057C7.5935 3.12998 8.21108 3.48504 8.89418 3.87825C10.0074 4.5184 11.2956 5.25926 13.7343 4.81506C16.3576 4.33721 17.7983 2.94565 18.0559 0.639794C17.2711 1.91222 16.1979 2.53116 14.8365 2.49695C14.0597 2.47714 13.4421 2.12208 12.7591 1.72919C11.6458 1.08905 10.3572 0.347928 7.91893 0.792063Z" fill="#937450"/><path fill-rule="evenodd" clip-rule="evenodd" d="M7.33322 2C5.02226 2 3.57774 3.11118 3 5.33355C3.86678 4.22237 4.87791 3.80576 6.03305 4.08339C6.69265 4.24175 7.16408 4.70173 7.68543 5.211C8.53523 6.04028 9.51851 7 11.6668 7C13.9777 7 15.4223 5.88914 16 3.66678C15.1332 4.77796 14.1221 5.19457 12.9666 4.91694C12.3074 4.75825 11.8359 4.29826 11.3146 3.78933C10.4648 2.96005 9.48115 2 7.33322 2Z" fill="#93C5FD"/></g><defs><clipPath id="clip0_2_9"><rect width="20" height="20" fill="white"/></clipPath></defs></svg>

          <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-50">Authorize your account</h2>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
          <div class="bg-white dark:bg-slate-800 dark:text-slate-50 py-8 px-4 shadow sm:rounded-lg sm:px-10">
            <form class="space-y-6" action="#" method="POST">

                <div class="card-body">
                    @php
                        $service = app(\App\Services\OAuth\TeamAuthorizationService::class);
                        $accessibleTeams = $service->getUserTeamsWithAccess(auth()->user(), $client);
                        $selectedTeam = $accessibleTeams->firstWhere('id', request('team_id'))
                            ?? request()->attributes->get('oauth_team')
                            ?? $accessibleTeams->first();
                    @endphp
                    <!-- Introduction -->
                    <p><strong>{{ $client->name }}</strong> is requesting permission to access your account. They'll be redirecting you to <strong>{{ $client->redirect }}</strong></p>
                    @if ($accessibleTeams->count() > 1)
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Authorize as team</label>
                            <select name="team_id" form="approve-form" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($accessibleTeams as $team)
                                    <option value="{{ $team->id }}" @selected($selectedTeam && $selectedTeam->id === $team->id)>
                                        {{ $team->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($selectedTeam)
                        <div class="mt-4 text-sm text-slate-600 dark:text-slate-300">
                            Authorizing as team: <strong>{{ $selectedTeam->name }}</strong>
                        </div>
                    @endif
                    <!-- Scope List -->
                    @if (count($scopes) > 0)
                        <div class="scopes">
                            <p><strong>This application will be able to:</strong></p>

                            <ul>
                                @foreach ($scopes as $scope)
                                    <li>{{ $scope->description }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="my-8 text-slate-400 dark:text-slate-300 font-semibold">
                        All authenticated apps will be able to access your name and email.
                    </div>
                    <div class="flex flex-wrap justify-between gap-4 mt-4">
                        <!-- Cancel Button -->
                        <form method="post" action="{{ route('passport.authorizations.deny') }}">
                            @csrf
                            @method('DELETE')

                            <input type="hidden" name="state" value="{{ $request->state }}">
                            <input type="hidden" name="client_id" value="{{ $client->id }}">
                            <input type="hidden" name="auth_token" value="{{ $authToken }}">
                            @if($selectedTeam)
                                <input type="hidden" name="team_id" value="{{ $selectedTeam->id }}">
                            @endif
                            <button class="flex justify-center rounded-md border-2 border-red-600 text-red-600 dark:border-red-400 dark:text-red-400 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Cancel</button>
                        </form>

                        <!-- Authorize Button -->
                        <form method="post" id="approve-form" action="{{ route('passport.authorizations.approve') }}">
                            @csrf

                            <input type="hidden" name="state" value="{{ $request->state }}">
                            <input type="hidden" name="client_id" value="{{ $client->id }}">
                            <input type="hidden" name="auth_token" value="{{ $authToken }}">
                            @if($selectedTeam)
                                <input type="hidden" name="team_id" value="{{ $selectedTeam->id }}">
                            @endif
                            <button type="submit" class="flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Authorize</button>
                        </form>
                    </div>
                </div>
            </form>
          </div>
        </div>
      </div>
</body>
</html>
