<div class="row g-3">
    <div class="col-lg-5">
        <div class="card toolbar-card"><div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-3"><i class="ti ti-lock text-primary"></i><h3 class="card-title mb-0">修改管理员密码</h3></div>
            <form method="post">
                <?= Csrf::field() ?>
                <div class="mb-3"><label class="form-label">当前密码</label><input class="form-control" type="password" name="current_password" required></div>
                <div class="mb-3"><label class="form-label">新密码</label><input class="form-control" type="password" name="new_password" minlength="8" maxlength="128" required></div>
                <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>保存密码</button>
            </form>
        </div></div>
    </div>
</div>
