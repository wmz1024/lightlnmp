<div class="card toolbar-card mb-3">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <div class="fw-semibold"><?= h($site['name']) ?></div>
            <div class="text-secondary small"><code>/<?= h($path) ?></code></div>
        </div>
        <form class="d-flex gap-2" method="get">
            <input type="hidden" name="r" value="files">
            <select class="form-select" name="site_id" onchange="this.form.submit()">
                <?php foreach ($sites as $row): ?><option value="<?= (int)$row['id'] ?>" <?= $row['id'] == $site['id'] ? 'selected' : '' ?>><?= h($row['name']) ?></option><?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
<?php
$editorExt = strtolower(pathinfo($editPath ?? '', PATHINFO_EXTENSION));
$editorLanguages = [
    'php' => 'php',
    'html' => 'html',
    'htm' => 'html',
    'css' => 'css',
    'js' => 'javascript',
    'json' => 'json',
    'md' => 'markdown',
    'xml' => 'xml',
];
$editorLanguage = $editorLanguages[$editorExt] ?? 'plaintext';
?>
<div class="row g-3 file-manager-row">
    <div class="col-lg-<?= $editContent !== null ? '4' : '12' ?>">
        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">文件列表</h3></div>
            <div class="card-body border-bottom">
                <form class="row g-2" method="post" enctype="multipart/form-data">
                    <?= Csrf::field() ?><input type="hidden" name="action" value="upload">
                    <div class="col"><input class="form-control" type="file" name="upload" required></div>
                    <div class="col-auto"><button class="btn btn-primary"><i class="ti ti-upload me-1"></i>上传</button></div>
                </form>
                <form class="row g-2 mt-2" method="post">
                    <?= Csrf::field() ?><input type="hidden" name="action" value="create">
                    <div class="col"><input class="form-control" name="name" placeholder="文件或目录名" required></div>
                    <div class="col-auto"><select class="form-select" name="type"><option value="file">文件</option><option value="dir">目录</option></select></div>
                    <div class="col-auto"><button class="btn btn-outline-primary"><i class="ti ti-plus me-1"></i>新建</button></div>
                </form>
            </div>
            <div class="table-responsive"><table class="table table-vcenter card-table table-hover">
                <thead><tr><th>名称</th><th>类型</th><th>大小</th><th>修改时间</th><th class="text-end">操作</th></tr></thead><tbody>
                <?php if ($path !== ''): $up = dirname($path); $up = $up === '.' ? '' : $up; ?><tr><td colspan="5"><a href="?r=files&site_id=<?= (int)$site['id'] ?>&path=<?= rawurlencode($up) ?>"><i class="ti ti-arrow-up me-1"></i>返回上级</a></td></tr><?php endif; ?>
                <?php foreach ($items as $item): $itemPath = trim($path . '/' . $item['name'], '/'); ?>
                    <tr>
                        <td><?= $item['type'] === 'dir' ? '<i class="ti ti-folder text-yellow me-2"></i><a class="fw-semibold" href="?r=files&site_id=' . (int)$site['id'] . '&path=' . rawurlencode($itemPath) . '">' . h($item['name']) . '</a>' : '<i class="ti ti-file-code text-secondary me-2"></i>' . h($item['name']) ?></td>
                        <td><span class="badge bg-secondary-lt text-secondary"><?= $item['type'] === 'dir' ? '目录' : '文件' ?></span></td>
                        <td class="text-secondary"><?= (int)$item['size'] ?></td>
                        <td class="text-secondary"><?= h($item['mtime']) ?></td>
                        <td><div class="action-row">
                            <?php if ($item['type'] === 'file'): ?><a class="btn btn-sm btn-outline-secondary" href="?r=files/download&site_id=<?= (int)$site['id'] ?>&path=<?= rawurlencode($itemPath) ?>"><i class="ti ti-download me-1"></i>下载</a><a class="btn btn-sm btn-outline-primary" href="?r=files&site_id=<?= (int)$site['id'] ?>&path=<?= rawurlencode($path) ?>&edit=<?= rawurlencode($itemPath) ?>"><i class="ti ti-edit me-1"></i>编辑</a><?php endif; ?>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#rename-modal" data-rename-target="<?= h($itemPath) ?>" data-rename-name="<?= h($item['name']) ?>"><i class="ti ti-pencil me-1"></i>重命名</button>
                            <form method="post" data-confirm="确认删除？"><?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="target" value="<?= h($itemPath) ?>"><button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash me-1"></i>删除</button></form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?><tr><td colspan="5" class="text-center text-secondary py-4">目录为空</td></tr><?php endif; ?>
                </tbody></table></div>
        </div>
    </div>
    <?php if ($editContent !== null): ?>
    <div class="col-lg-8">
        <div class="card editor-card">
            <div class="card-header editor-card-header">
                <div>
                    <h3 class="card-title mb-1">编辑文件</h3>
                    <div class="text-secondary small text-truncate editor-path"><code><?= h($editPath) ?></code></div>
                </div>
                <a class="btn btn-outline-secondary" href="?r=files&site_id=<?= (int)$site['id'] ?>&path=<?= rawurlencode($path) ?>"><i class="ti ti-x me-1"></i>关闭</a>
            </div>
            <form method="post" class="monaco-form" data-editor-language="<?= h($editorLanguage) ?>">
                <div class="card-body editor-card-body">
                    <?= Csrf::field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="target" value="<?= h($editPath) ?>">
                    <div class="monaco-editor-shell">
                        <div class="monaco-editor-toolbar">
                            <span class="badge bg-blue-lt text-blue"><?= h($editorLanguage) ?></span>
                            <span class="text-secondary small monaco-editor-status">Ln 1, Col 1</span>
                        </div>
                        <div class="monaco-editor" data-editor-target></div>
                    </div>
                    <textarea class="form-control code-editor monaco-source mb-3" name="content" spellcheck="false"><?= h($editContent) ?></textarea>
                </div>
                <div class="card-footer editor-card-footer">
                    <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>保存文件</button>
                    <span class="text-secondary small monaco-load-state">Textarea</span>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<div class="modal modal-blur fade" id="rename-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <form class="modal-content" method="post">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="target" id="rename-target">
            <div class="modal-header"><h5 class="modal-title">重命名</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><label class="form-label">新名称</label><input class="form-control" name="new_name" id="rename-name" required></div>
            <div class="modal-footer"><button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary">保存</button></div>
        </form>
    </div>
</div>
