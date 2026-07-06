<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <div><h3 class="card-title mb-0"><?= h($site['name']) ?> 日志</h3><div class="text-secondary small">最近 200 行</div></div>
        <div class="btn-list">
            <?php foreach (['access' => '访问日志', 'error' => '错误日志', 'ssl-access' => 'HTTPS 访问', 'ssl-error' => 'HTTPS 错误'] as $key => $label): ?>
                <a class="btn btn-sm <?= $type === $key ? 'btn-primary' : 'btn-outline-secondary' ?>" href="?r=sites/logs&id=<?= (int)$site['id'] ?>&type=<?= h($key) ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
            <a class="btn btn-sm btn-outline-secondary" href="?r=sites"><i class="ti ti-arrow-left me-1"></i>返回</a>
        </div>
    </div>
    <div class="card-body p-0"><pre class="log-viewer mb-0"><?= h($logOutput ?: ($ok ? '日志为空' : '无法读取日志')) ?></pre></div>
</div>
