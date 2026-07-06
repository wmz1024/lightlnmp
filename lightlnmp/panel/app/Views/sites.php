<div class="row g-3">
    <div class="col-lg-4">
        <div class="card toolbar-card"><div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-3"><i class="ti ti-plus text-primary"></i><h3 class="card-title mb-0">创建站点</h3></div>
            <form method="post">
                <?= Csrf::field() ?>
                <div class="mb-3"><label class="form-label">站点名</label><input class="form-control" name="name" placeholder="example.com" required></div>
                <div class="mb-3"><label class="form-label">域名或公网 IP</label><input class="form-control" name="domain" placeholder="example.com" required></div>
                <button class="btn btn-primary w-100"><i class="ti ti-circle-plus me-1"></i>创建站点</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">站点列表</h3></div>
            <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
                <thead><tr><th>名称</th><th>根目录</th><th>状态</th><th>操作</th></tr></thead>
                <tbody>
                <?php foreach ($sites as $site): ?>
                    <tr>
                        <td><div class="fw-semibold"><?= h($site['name']) ?></div><div class="text-secondary small">创建于 <?= h($site['created_at']) ?></div></td><td><code><?= h($site['root']) ?></code></td>
                        <td><span class="badge <?= $site['enabled'] ? 'bg-green-lt text-green' : 'bg-secondary-lt text-secondary' ?>"><?= $site['enabled'] ? '启用' : '停用' ?></span></td>
                        <td><div class="action-row">
                            <a class="btn btn-sm btn-outline-primary" href="?r=files&site_id=<?= (int)$site['id'] ?>"><i class="ti ti-folder me-1"></i>文件</a>
                            <form method="post" action="?r=sites/action"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$site['id'] ?>"><input type="hidden" name="action" value="<?= $site['enabled'] ? 'disable' : 'enable' ?>"><button class="btn btn-sm btn-outline-secondary"><?= $site['enabled'] ? '停用' : '启用' ?></button></form>
                            <form method="post" action="?r=sites/action" data-confirm="确认删除站点和文件？"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$site['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash me-1"></i>删除</button></form>
                        </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$sites): ?><tr><td colspan="4" class="text-center text-secondary py-4">暂无站点</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>
