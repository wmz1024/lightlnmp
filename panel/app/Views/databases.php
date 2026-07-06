<div class="row g-3">
    <div class="col-lg-4"><div class="card toolbar-card"><div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3"><i class="ti ti-database-plus text-primary"></i><h3 class="card-title mb-0">创建用户与数据库</h3></div>
        <form method="post">
            <?= Csrf::field() ?><input type="hidden" name="action" value="create-user-db">
            <div class="mb-2"><label class="form-label">数据库名</label><input class="form-control" name="database" placeholder="database_name" required></div>
            <div class="mb-2"><label class="form-label">字符编码</label><select class="form-select" name="charset"><?php foreach ($charsets as $charset => $collation): ?><option value="<?= h($charset) ?>"><?= h($charset) ?> / <?= h($collation) ?></option><?php endforeach; ?></select></div>
            <div class="mb-2"><label class="form-label">用户名</label><input class="form-control" name="user" placeholder="db_user" required></div>
            <div class="mb-3"><label class="form-label">密码</label><input class="form-control" name="password" placeholder="至少 8 位" required></div>
            <button class="btn btn-primary w-100"><i class="ti ti-circle-plus me-1"></i>创建并授权</button>
        </form>
        <hr>
        <div class="d-flex align-items-center gap-2 mb-3"><i class="ti ti-database text-primary"></i><h3 class="card-title mb-0">仅创建数据库</h3></div>
        <form method="post">
            <?= Csrf::field() ?><input type="hidden" name="action" value="create">
            <div class="mb-2"><input class="form-control" name="name" placeholder="database_name" required></div>
            <div class="mb-3"><select class="form-select" name="charset"><?php foreach ($charsets as $charset => $collation): ?><option value="<?= h($charset) ?>"><?= h($charset) ?> / <?= h($collation) ?></option><?php endforeach; ?></select></div>
            <button class="btn btn-outline-primary w-100">创建数据库</button>
        </form>
    </div></div></div>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap"><h3 class="card-title mb-0">数据库列表</h3><span class="text-secondary small">共 <?= (int)$dbPager['total'] ?> 个</span></div>
            <div class="card-body border-bottom">
                <form id="db-batch-form" method="post" data-confirm="确认删除选中数据库？">
                    <?= Csrf::field() ?><input type="hidden" name="action" value="batch-delete">
                    <button class="btn btn-outline-danger"><i class="ti ti-trash me-1"></i>删除选中</button>
                </form>
            </div>
            <div class="table-responsive"><table class="table table-vcenter card-table table-hover"><thead><tr><th class="w-1"><input class="form-check-input" type="checkbox" data-check-all="db-row-check"></th><th>数据库</th><th class="text-end">操作</th></tr></thead><tbody>
                <?php foreach ($databases as $db): ?><tr><td><input class="form-check-input db-row-check" type="checkbox" name="names[]" value="<?= h($db) ?>" form="db-batch-form"></td><td><i class="ti ti-database me-2 text-primary"></i><?= h($db) ?></td><td><div class="action-row"><form method="post" data-confirm="确认删除数据库？"><?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="name" value="<?= h($db) ?>"><button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash me-1"></i>删除</button></form></div></td></tr><?php endforeach; ?>
                <?php if (!$databases): ?><tr><td colspan="3" class="text-center text-secondary py-4">暂无数据库或 MariaDB 未安装</td></tr><?php endif; ?>
            </tbody></table></div>
            <?php if ($dbPager['pages'] > 1): ?><div class="card-footer d-flex justify-content-end"><ul class="pagination m-0"><?php for ($i = 1; $i <= $dbPager['pages']; $i++): ?><li class="page-item <?= $i === $dbPager['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= h(query_url([$dbPager['param'] => $i])) ?>"><?= $i ?></a></li><?php endfor; ?></ul></div><?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap"><h3 class="card-title mb-0">数据库账户</h3><span class="text-secondary small">共 <?= (int)$dbUserPager['total'] ?> 个</span></div>
            <div class="table-responsive"><table class="table table-vcenter card-table table-hover"><thead><tr><th>用户</th><th>主机</th><th>授权数据库</th><th>新密码</th><th class="text-end">操作</th></tr></thead><tbody>
                <?php foreach ($dbUsers as $row): ?><tr>
                    <td><i class="ti ti-user me-2 text-primary"></i><?= h($row['user']) ?></td>
                    <td class="text-secondary"><?= h($row['host']) ?></td>
                    <td><form class="d-flex gap-2" method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="grant"><input type="hidden" name="user" value="<?= h($row['user']) ?>"><select class="form-select form-select-sm" name="database" required><?php foreach ($allDatabases as $db): ?><option value="<?= h($db) ?>"><?= h($db) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-primary">授权</button></form></td>
                    <td><form class="d-flex gap-2" method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="user-password"><input type="hidden" name="user" value="<?= h($row['user']) ?>"><input class="form-control form-control-sm" name="password" placeholder="至少 8 位" required><button class="btn btn-sm btn-outline-secondary">修改</button></form></td>
                    <td><div class="action-row"><form method="post" data-confirm="确认删除数据库账户？"><?= Csrf::field() ?><input type="hidden" name="action" value="user-delete"><input type="hidden" name="user" value="<?= h($row['user']) ?>"><button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash me-1"></i>删除</button></form></div></td>
                </tr><?php endforeach; ?>
                <?php if (!$dbUsers): ?><tr><td colspan="5" class="text-center text-secondary py-4">暂无数据库账户或 MariaDB 未安装</td></tr><?php endif; ?>
            </tbody></table></div>
            <?php if ($dbUserPager['pages'] > 1): ?><div class="card-footer d-flex justify-content-end"><ul class="pagination m-0"><?php for ($i = 1; $i <= $dbUserPager['pages']; $i++): ?><li class="page-item <?= $i === $dbUserPager['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= h(query_url([$dbUserPager['param'] => $i])) ?>"><?= $i ?></a></li><?php endfor; ?></ul></div><?php endif; ?>
        </div>
    </div>
</div>
