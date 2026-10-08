<x-layouts.guest title="Nova senha">
<span class="eyebrow">SUA CONTA</span><h2>Defina uma nova senha</h2><p class="muted">Use 12 ou mais caracteres, com maiúsculas, minúsculas e números.</p>
@if($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>@endif
<form class="auth-form" method="post" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}">
<label for="email">E-mail</label><input id="email" name="email" type="email" value="{{ old('email',request('email')) }}" required autocomplete="username">
<label for="password">Nova senha</label><input id="password" name="password" type="password" required autocomplete="new-password">
<label for="password_confirmation">Confirme a senha</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
<button class="button primary full">Salvar nova senha</button></form></x-layouts.guest>
