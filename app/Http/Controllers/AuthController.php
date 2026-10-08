<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Hash, Password, RateLimiter};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
class AuthController
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required','email'], 'password' => ['required','string']]);
        $data['email'] = Str::lower(trim($data['email']));
        $key = 'login:'.hash('sha256', $data['email'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5))
            throw ValidationException::withMessages(['email' => 'Muitas tentativas. Aguarde '.RateLimiter::availableIn($key).' segundos.']);
        if (!Auth::attempt([...$data, 'active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos, ou acesso inativo.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        Audit::record('login', 'users', Auth::id());
        return redirect()->intended(route('dashboard'));
    }
    public function logout(Request $request)
    {
        Audit::record('logout', 'users', Auth::id());
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    }
    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => ['required','email']]);
        Password::sendResetLink(['email' => Str::lower(trim($data['email']))]);
        return back()->with('status', 'Se houver uma conta para este e-mail, você receberá as instruções de recuperação.');
    }
    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required'], 'email' => ['required','email'],
            'password' => ['required','confirmed', PasswordRule::min(12)->mixedCase()->numbers()],
        ]);
        $data['email'] = Str::lower(trim($data['email']));
        $status = Password::reset($data, function (User $user, string $password) {
            DB::transaction(function () use ($user, $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                Audit::record('senha_redefinida', 'users', $user->id);
            });
        });
        if ($status !== Password::PASSWORD_RESET)
            throw ValidationException::withMessages(['email' => 'O link é inválido ou expirou. Solicite uma nova recuperação.']);
        return redirect()->route('login')->with('status', 'Senha redefinida. Entre com sua nova senha.');
    }
}
