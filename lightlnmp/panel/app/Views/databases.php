<div class="row g-3">
    <div class="col-lg-4"><div class="card toolbar-card"><div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3"><i class="ti ti-database-plus text-primary"></i><h3 class="card-title mb-0">创建数据库</h3></div>
        <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="create"><div class="mb-3"><input class="form-control" name="name" placeholder="database_name" required></div><button class="btn btn-primary w-100">创建数据库</button></form>
        <hr><div class="d-flex align-items-center gap-2 mb-3"><i class="ti ti-user-plus text-primary"></i><h3 class="card-title mb-0">创建用户并授权</h3></div>
        <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="user-create"><div class="mb-2"><input class="form-control" name="user" placeholder="用户名" required></div><div class="mb-2"><input class="form-control" name="password" placeholder="密码，至少 8 位" required></div><div class="mb-3"><input class="form-control" name="database" placeholder="授权数据库，可留空"></div><button class="btn btn-outline-primary w-100">创建用户</button></form>
    </div></div></div>
    <div class="col-lg-8"><div class="card"><div class="card-header"><h3 class="card-title mb-0">数据库列表</h3></div><div class="table-responsive"><table class="table table-vcenter card-table table-hover"><thead><tr><th>数据库</th><th class="text-end">操作</th></tr></thead><tbody>
        <?php foreach ($databases as $db): ?><tr><td><i class="ti ti-database me-2 text-primary"></i><?= h($db) ?></td><td><div class="action-row"><form method="post" data-confirm="确认删除数据库？"><?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="name" value="<?= h($db) ?>"><button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash me-1"></i>删除</button></form></div></td></tr><?php endforeach; ?>
        <?php if (!$databases): ?><tr><td colspan="2" class="text-center text-secondary py-4">暂无数据库或 MariaDB 未安装</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
</div>
