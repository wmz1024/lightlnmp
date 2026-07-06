<div class="login-wrap">
    <div class="text-center mb-4">
        <div class="brand-mark mx-auto mb-3">L</div>
        <h1 class="h2 mb-1">LightLNMP</h1>
        <div class="text-secondary">轻量 WebHosting 控制台</div>
    </div>
    <div class="card card-md shadow-sm">
        <div class="card-body p-4">
            <h2 class="h3 text-center mb-4">管理员登录</h2>
            <?php if ($flash): ?>
                <div class="alert alert-<?= h($flash['type']) ?>" role="alert"><?= h($flash['message']) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <?= Csrf::field() ?>
                <div class="mb-3">
                    <label class="form-label">用户名</label>
                    <div class="input-icon"><span class="input-icon-addon"><i class="ti ti-user"></i></span><input class="form-control" name="username" required autofocus></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">密码</label>
                    <div class="input-icon"><span class="input-icon-addon"><i class="ti ti-lock"></i></span><input class="form-control" type="password" name="password" required></div>
                </div>
                <button class="btn btn-primary w-100"><i class="ti ti-login me-1"></i>登录</button>
            </form>
        </div>
    </div>
</div>
