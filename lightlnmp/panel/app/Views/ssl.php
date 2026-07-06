<div class="row g-3">
    <div class="col-lg-4"><div class="card toolbar-card"><div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3"><i class="ti ti-shield-lock text-primary"></i><h3 class="card-title mb-0">申请证书</h3></div>
        <form method="post">
            <?= Csrf::field() ?><input type="hidden" name="action" value="issue">
            <div class="mb-3"><label class="form-label">站点</label><select class="form-select" name="site_id"><?php foreach ($sites as $site): ?><option value="<?= (int)$site['id'] ?>"><?= h($site['name']) ?></option><?php endforeach; ?></select></div>
            <div class="mb-3"><label class="form-label">域名或公网 IP</label><input class="form-control" name="identifier" placeholder="example.com / 203.0.113.10" required></div>
            <label class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="force_https"><span class="form-check-label">强制 HTTPS</span></label>
            <button class="btn btn-primary w-100"><i class="ti ti-certificate me-1"></i>申请证书</button>
        </form>
        <hr><form method="post" data-confirm="确认续期全部证书？"><?= Csrf::field() ?><input type="hidden" name="action" value="renew-all"><button class="btn btn-outline-primary w-100"><i class="ti ti-refresh me-1"></i>续期全部</button></form>
    </div></div></div>
    <div class="col-lg-8"><div class="card"><div class="card-header"><h3 class="card-title mb-0">证书列表</h3></div><div class="table-responsive"><table class="table table-vcenter card-table table-hover"><thead><tr><th>站点</th><th>标识</th><th>类型</th><th>状态</th><th>更新时间</th></tr></thead><tbody>
        <?php foreach ($certificates as $cert): ?><tr><td><?= h($cert['site_name']) ?></td><td><code><?= h($cert['identifier']) ?></code></td><td><span class="badge bg-blue-lt text-blue"><?= h($cert['identifier_type']) ?></span></td><td><span class="badge <?= $cert['status'] === 'issued' ? 'bg-green-lt text-green' : 'bg-red-lt text-red' ?>"><?= h($cert['status']) ?></span></td><td class="text-secondary"><?= h($cert['updated_at']) ?></td></tr><?php endforeach; ?>
        <?php if (!$certificates): ?><tr><td colspan="5" class="text-center text-secondary py-4">暂无证书</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
</div>
