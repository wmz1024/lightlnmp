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
    <div class="col-lg-8"><div class="card"><div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap"><h3 class="card-title mb-0">证书列表</h3><span class="text-secondary small">共 <?= (int)$certPager['total'] ?> 个</span></div>
    <div class="card-body border-bottom"><form id="cert-batch-form" method="post" data-confirm="确认续期选中证书？"><?= Csrf::field() ?><input type="hidden" name="action" value="renew-selected"><button class="btn btn-outline-primary"><i class="ti ti-refresh me-1"></i>续期选中</button></form></div>
    <div class="table-responsive"><table class="table table-vcenter card-table table-hover"><thead><tr><th class="w-1"><input class="form-check-input" type="checkbox" data-check-all="cert-row-check"></th><th>站点</th><th>标识</th><th>类型</th><th>状态</th><th>更新时间</th></tr></thead><tbody>
        <?php foreach ($certificates as $cert): ?><tr><td><input class="form-check-input cert-row-check" type="checkbox" name="ids[]" value="<?= (int)$cert['id'] ?>" form="cert-batch-form"></td><td><?= h($cert['site_name']) ?></td><td><code><?= h($cert['identifier']) ?></code></td><td><span class="badge bg-blue-lt text-blue"><?= h($cert['identifier_type']) ?></span></td><td><span class="badge <?= $cert['status'] === 'issued' ? 'bg-green-lt text-green' : 'bg-red-lt text-red' ?>"><?= h($cert['status']) ?></span></td><td class="text-secondary"><?= h($cert['updated_at']) ?></td></tr><?php endforeach; ?>
        <?php if (!$certificates): ?><tr><td colspan="6" class="text-center text-secondary py-4">暂无证书</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php if ($certPager['pages'] > 1): ?><div class="card-footer d-flex justify-content-end"><ul class="pagination m-0"><?php for ($i = 1; $i <= $certPager['pages']; $i++): ?><li class="page-item <?= $i === $certPager['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= h(query_url([$certPager['param'] => $i])) ?>"><?= $i ?></a></li><?php endfor; ?></ul></div><?php endif; ?>
    </div></div>
</div>
