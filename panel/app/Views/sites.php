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
            <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap"><h3 class="card-title mb-0">站点列表</h3><span class="text-secondary small">共 <?= (int)$sitesPager['total'] ?> 个</span></div>
            <div class="card-body border-bottom">
                <form id="site-batch-form" method="post" data-confirm="确认执行批量操作？">
                    <?= Csrf::field() ?><input type="hidden" name="action" value="batch">
                    <div class="d-flex gap-2 flex-wrap">
                        <select class="form-select w-auto" name="batch_action" required><option value="">批量操作</option><option value="enable">启用</option><option value="disable">停用</option><option value="delete">删除</option></select>
                        <button class="btn btn-outline-primary"><i class="ti ti-checks me-1"></i>应用</button>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
                <thead><tr><th class="w-1"><input class="form-check-input" type="checkbox" data-check-all="site-row-check"></th><th>名称</th><th>根目录</th><th>状态</th><th>操作</th></tr></thead>
                <tbody>
                <?php foreach ($sites as $site): $rewriteMode = $site['rewrite_mode'] ?? 'preset'; $rewriteRule = $site['rewrite_rule'] ?? 'default'; ?>
                    <tr>
                        <td><input class="form-check-input site-row-check" type="checkbox" name="ids[]" value="<?= (int)$site['id'] ?>" form="site-batch-form"></td>
                        <td><div class="fw-semibold"><?= h($site['name']) ?></div><div class="text-secondary small">创建于 <?= h($site['created_at']) ?></div></td>
                        <td><code><?= h($site['root']) ?></code><div class="text-secondary small mt-1">HTTP <?= (int)($site['http_port'] ?? 80) ?> / HTTPS <?= (int)($site['https_port'] ?? 443) ?> / <?= $rewriteMode === 'custom' ? '自定义伪静态' : h($rewriteRules[$rewriteRule] ?? $rewriteRule) ?></div></td>
                        <td><span class="badge <?= $site['enabled'] ? 'bg-green-lt text-green' : 'bg-secondary-lt text-secondary' ?>"><?= $site['enabled'] ? '启用' : '停用' ?></span></td>
                        <td><div class="action-row">
                            <a class="btn btn-sm btn-outline-primary" href="?r=files&site_id=<?= (int)$site['id'] ?>"><i class="ti ti-folder me-1"></i>文件</a>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#site-config-modal" data-site-id="<?= (int)$site['id'] ?>" data-site-name="<?= h($site['name']) ?>" data-site-http-port="<?= (int)($site['http_port'] ?? 80) ?>" data-site-https-port="<?= (int)($site['https_port'] ?? 443) ?>" data-site-rewrite-mode="<?= h($rewriteMode) ?>" data-site-rewrite-rule="<?= h($rewriteRule) ?>" data-site-rewrite-custom="<?= h($site['rewrite_custom'] ?? '') ?>"><i class="ti ti-settings me-1"></i>配置</button>
                            <a class="btn btn-sm btn-outline-secondary" href="?r=sites/logs&id=<?= (int)$site['id'] ?>&type=access"><i class="ti ti-file-text me-1"></i>日志</a>
                            <form method="post" action="?r=sites/action"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$site['id'] ?>"><input type="hidden" name="action" value="<?= $site['enabled'] ? 'disable' : 'enable' ?>"><button class="btn btn-sm btn-outline-secondary"><?= $site['enabled'] ? '停用' : '启用' ?></button></form>
                            <form method="post" action="?r=sites/action" data-confirm="确认删除站点和文件？"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$site['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash me-1"></i>删除</button></form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$sites): ?><tr><td colspan="5" class="text-center text-secondary py-4">暂无站点</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($sitesPager['pages'] > 1): ?><div class="card-footer d-flex justify-content-end"><ul class="pagination m-0">
            <?php for ($i = 1; $i <= $sitesPager['pages']; $i++): ?><li class="page-item <?= $i === $sitesPager['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= h(query_url([$sitesPager['param'] => $i])) ?>"><?= $i ?></a></li><?php endfor; ?>
        </ul></div><?php endif; ?>
        </div>
    </div>
</div>

<div class="modal modal-blur fade" id="site-config-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form class="modal-content" method="post" action="?r=sites/action">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" id="site-config-id">
            <input type="hidden" name="action" value="config">
            <div class="modal-header"><h5 class="modal-title">站点配置</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">站点</label><input class="form-control" id="site-config-name" disabled></div>
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label">HTTP 端口</label><input class="form-control" type="number" name="http_port" id="site-config-http-port" min="1" max="65535" required></div>
                    <div class="col-md-6"><label class="form-label">HTTPS 端口</label><input class="form-control" type="number" name="https_port" id="site-config-https-port" min="1" max="65535" required></div>
                </div>
                <div class="text-secondary small mt-2 mb-3">公网证书 HTTP-01 验证通常需要 HTTP 端口保持 80。</div>
                <div class="mb-3"><label class="form-label">伪静态模式</label><select class="form-select" name="rewrite_mode" id="site-config-rewrite-mode"><option value="preset">内置模板</option><option value="custom">自定义配置</option></select></div>
                <div class="mb-3" data-rewrite-preset><label class="form-label">内置模板</label><select class="form-select" name="rewrite_rule" id="site-config-rewrite-rule"><?php foreach ($rewriteRules as $key => $label): ?><option value="<?= h($key) ?>"><?= h($label) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3" data-rewrite-custom><label class="form-label">自定义伪静态</label><textarea class="form-control code-editor-light" name="rewrite_custom" id="site-config-rewrite-custom" rows="10" spellcheck="false"></textarea><div class="form-hint">只允许 location 内规则。禁止 server、listen、root、location、include、alias、ssl_certificate 等指令。</div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>保存配置</button></div>
        </form>
    </div>
</div>
