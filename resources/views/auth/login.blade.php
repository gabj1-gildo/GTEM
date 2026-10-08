<x-layouts.guest title="Entrar">
<span class="eyebrow">AMBIENTE ADMINISTRATIVO</span><h2>Bem-vindo ao GTEM</h2><p class="muted">Entre com sua conta para continuar.</p>
@if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('login') }}" class="auth-form">@csrf
<label for="email">E-mail institucional</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="seu.nome@municipio.gov.br">
<div class="label-row"><label for="password">Senha</label><a href="{{ route('password.request') }}">Esqueci minha senha</a></div>
<input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Digite sua senha">
<label class="checkbox-label"><input type="checkbox" name="remember" value="1"> Manter conectado neste dispositivo</label>
<button class="button primary full" type="submit">Acessar o sistema <x-icon name="arrow"/></button>
</form>
<div class="auth-help"><x-icon name="shield"/><span>Ainda não tem acesso?<br><strong>Solicite uma conta à administração.</strong></span></div>
</x-layouts.guest>
