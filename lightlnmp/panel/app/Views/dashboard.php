<div class="row row-deck row-cards mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card"><div class="card-body d-flex align-items-center gap-3">
            <span class="metric-icon bg-primary-lt text-primary"><i class="ti ti-world-www fs-2"></i></span>
            <div><div class="text-secondary">站点数量</div><div class="h2 mb-0"><?= (int)$siteCount ?></div></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card"><div class="card-body d-flex align-items-center gap-3">
            <span class="metric-icon bg-green-lt text-green"><i class="ti ti-shield-check fs-2"></i></span>
            <div><div class="text-secondary">有效证书</div><div class="h2 mb-0"><?= (int)$certCount ?></div></div>
        </div></div>
    </div>
    <?php foreach ($statuses as $name => $status): ?>
    <div class="col-sm-6 col-lg-3">
        <div class="card"><div class="card-body d-flex align-items-center justify-content-between">
            <div><div class="text-secondary"><?= h($name) ?></div><div class="fw-semibold">服务状态</div></div>
            <span class="badge <?= $status === 'running' ? 'bg-green-lt text-green' : 'bg-secondary-lt text-secondary' ?>"><?= $status === 'running' ? '运行中' : '已停止' ?></span>
        </div></div>
    </div>
    <?php endforeach; ?>
</div>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">最近操作</h3>
        <span class="text-secondary small">最近 10 条</span>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead><tr><th>时间</th><th>动作</th><th>目标</th><th>结果</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr><td class="text-secondary"><?= h($log['created_at']) ?></td><td><code><?= h($log['action']) ?></code></td><td><?= h($log['target']) ?></td><td><span class="badge <?= $log['result'] === 'ok' ? 'bg-green-lt text-green' : 'bg-red-lt text-red' ?>"><?= h($log['result']) ?></span></td></tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?><tr><td colspan="4" class="text-center text-secondary py-4">暂无操作记录</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
