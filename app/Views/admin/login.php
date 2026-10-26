<section class="admin-login-card" aria-labelledby="login-title">
    <a class="admin-brand admin-login-brand" href="/"><span class="admin-brand-mark">A</span><span><small>FERRETERÍA</small>AYALA</span></a>
    <p class="admin-kicker">ACCESO INTERNO</p>
    <h1 id="login-title">Panel administrativo</h1>
    <p class="admin-lead">Ingresa con la cuenta creada de forma segura por el propietario.</p>
    <?php if (!empty($error)): ?><div class="admin-alert admin-alert-error" role="alert"><?= e($error) ?></div><?php endif ?>
    <form method="post" action="/admin/login" class="admin-form">
        <input type="hidden" name="_token" value="<?= e($csrf) ?>">
        <label class="admin-field"><span>Correo electrónico</span><input type="email" name="email" autocomplete="username" maxlength="254" required value="<?= e($email ?? '') ?>"></label>
        <label class="admin-field"><span>Contraseña</span><input type="password" name="password" autocomplete="current-password" maxlength="1024" required></label>
        <button class="admin-primary-button" type="submit">Iniciar sesión</button>
    </form>
    <p class="admin-login-note">Acceso exclusivo para personal autorizado. Los intentos se registran y limitan.</p>
</section>
