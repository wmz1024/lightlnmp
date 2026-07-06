<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">服务列表</h3></div>
    <div class="table-responsive"><table class="table table-vcenter card-table table-hover"><thead><tr><th>服务</th><th>状态</th><th class="text-end">操作</th></tr></thead><tbody>
<?php foreach ($statuses as $service => $status): ?>
<tr>
    <td><i class="ti ti-server me-2 text-primary"></i><span class="fw-semibold"><?= h($service) ?></span></td>
    <td><span class="badge <?= $status === 'running' ? 'bg-green-lt text-green' : 'bg-secondary-lt text-secondary' ?>"><?= $status === 'running' ? '运行中' : '已停止' ?></span></td>
    <td><div class="action-row">
<?php foreach (['start' => '启动', 'stop' => '停止', 'restart' => '重启'] as $action => $label): ?><form method="post"><?= Csrf::field() ?><input type="hidden" name="service" value="<?= h($service) ?>"><input type="hidden" name="action" value="<?= h($action) ?>"><button class="btn btn-sm btn-outline-primary"><?= h($label) ?></button></form><?php endforeach; ?>
    </div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div>
