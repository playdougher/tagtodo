<?php
// 极简共享 Todo - Tag 为核心的操作方式
$file = __DIR__ . '/todos.json';
$tags_file = __DIR__ . '/tags.json';
$admin_password = ''; // 设密码则修改时需要输入

$todos = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
$preset_tags = file_exists($tags_file) ? json_decode(file_get_contents($tags_file), true) : [];
// json_decode 可能返回 null（文件损坏），兜底为空数组
if (!is_array($todos)) $todos = [];
if (!is_array($preset_tags)) $preset_tags = [];

// AJAX 请求处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    $pw = $input['pw'] ?? '';
    if ($admin_password !== '' && $pw !== $admin_password) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '密码错误']);
        exit;
    }

    $action = $input['action'] ?? '';
    $idx = (int)($input['idx'] ?? -1);
    $result = ['ok' => true];

    if ($action === 'add') {
        $tags = $input['tags'] ?? [];
        $text = trim($input['text'] ?? '');
        if (!empty($tags) || $text !== '') {
            array_unshift($todos, [
                'tags' => $tags,
                'text' => $text,
                'done' => false,
                'created_at' => time(),
                'done_at' => null
            ]);
            file_put_contents($file, json_encode($todos, JSON_PRETTY_PRINT));
        }
    } elseif ($action === 'edit' && isset($todos[$idx])) {
        $tags = $input['tags'] ?? $todos[$idx]['tags'] ?? [];
        $text = trim($input['text'] ?? '');
        $todos[$idx]['tags'] = $tags;
        $todos[$idx]['text'] = $text;
        file_put_contents($file, json_encode($todos, JSON_PRETTY_PRINT));
    } elseif ($action === 'toggle' && isset($todos[$idx])) {
        $todos[$idx]['done'] = !$todos[$idx]['done'];
        $todos[$idx]['done_at'] = $todos[$idx]['done'] ? time() : null;
        file_put_contents($file, json_encode($todos, JSON_PRETTY_PRINT));
    } elseif ($action === 'delete' && isset($todos[$idx])) {
        array_splice($todos, $idx, 1);
        file_put_contents($file, json_encode($todos, JSON_PRETTY_PRINT));
    } elseif ($action === 'add_preset_tag') {
        $gi = (int)($input['gi'] ?? 0);
        $tag = trim($input['tag'] ?? '');
        if ($tag !== '' && isset($preset_tags[$gi])) {
            if (!in_array($tag, $preset_tags[$gi]['tags'])) {
                $preset_tags[$gi]['tags'][] = $tag;
                file_put_contents($tags_file, json_encode($preset_tags, JSON_UNESCAPED_UNICODE));
            }
        }
        $result['groups'] = $preset_tags;
    } elseif ($action === 'delete_preset_tag') {
        $gi = (int)($input['gi'] ?? 0);
        $tag = trim($input['tag'] ?? '');
        if (isset($preset_tags[$gi])) {
            $preset_tags[$gi]['tags'] = array_values(array_filter($preset_tags[$gi]['tags'], fn($t) => $t !== $tag));
            file_put_contents($tags_file, json_encode($preset_tags, JSON_UNESCAPED_UNICODE));
        }
        $result['groups'] = $preset_tags;
    } elseif ($action === 'add_group') {
        $name = trim($input['name'] ?? '');
        if ($name !== '') {
            $preset_tags[] = ['name' => $name, 'tags' => []];
            file_put_contents($tags_file, json_encode($preset_tags, JSON_UNESCAPED_UNICODE));
        }
        $result['groups'] = $preset_tags;
    } elseif ($action === 'rename_group') {
        $gi = (int)($input['gi'] ?? -1);
        $name = trim($input['name'] ?? '');
        if (isset($preset_tags[$gi]) && $name !== '') {
            $preset_tags[$gi]['name'] = $name;
            file_put_contents($tags_file, json_encode($preset_tags, JSON_UNESCAPED_UNICODE));
        }
        $result['groups'] = $preset_tags;
    } elseif ($action === 'delete_group') {
        $gi = (int)($input['gi'] ?? -1);
        if (isset($preset_tags[$gi])) {
            array_splice($preset_tags, $gi, 1);
            file_put_contents($tags_file, json_encode($preset_tags, JSON_UNESCAPED_UNICODE));
        }
        $result['groups'] = $preset_tags;
    } elseif ($action === 'move_group_to') {
        $gi = (int)($input['gi'] ?? -1);
        $to = (int)($input['to'] ?? -1);
        if (isset($preset_tags[$gi]) && $to >= 0 && $to < count($preset_tags) && $gi !== $to) {
            $group = array_splice($preset_tags, $gi, 1)[0];
            array_splice($preset_tags, $to, 0, [$group]);
            file_put_contents($tags_file, json_encode($preset_tags, JSON_UNESCAPED_UNICODE));
        }
        $result['groups'] = $preset_tags;
    } else {
        $result = ['error' => '无效操作'];
    }

    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

function format_time($ts) { return date('Y-m-d H:i', $ts); }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>共享 Todo</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 600px;
            margin: 2rem auto;
            padding: 0 1rem;
            background: #fafafa;
            position: relative;
        }
        input[type="text"] {
            padding: 10px;
            font-size: 1rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        ul { list-style: none; padding: 0; }
        li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: baseline;
            cursor: pointer;
        }
        .todo-text {
            flex-grow: 1;
            word-break: break-word;
        }
        .todo-text.done {
            text-decoration: line-through;
            color: #888;
        }
        .edit-input {
            flex-grow: 1;
            font-size: 1rem;
            padding: 2px 6px;
            border: 1px solid #4a90d9;
            border-radius: 3px;
            outline: none;
            font-family: inherit;
        }
        .timestamp {
            font-size: 0.88rem;
            color: #666;
            margin-left: 12px;
            flex-shrink: 0;
        }
        .delete-link {
            margin-left: 8px;
            color: #999;
            cursor: pointer;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .delete-link:hover { color: #f44336; }
        small { color: #666; }
        .tips {
            position: fixed;
            top: 2rem;
            left: calc(50% + 340px);
            width: 160px;
            font-size: 0.78rem;
            color: #999;
            line-height: 1.8;
        }
        @media (max-width: 900px) {
            .tips { display: none; }
        }
        .section-title {
            margin-top: 2rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: #666;
            border-bottom: 1px solid #ddd;
            padding-bottom: 4px;
        }
        .done-section li {
            opacity: 0.7;
        }

        /* Tag 样式 */
        .tag-bar {
            margin-bottom: 6px;
        }
        .tag-group {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            padding: 24px 10px 8px 10px;
            margin-bottom: 6px;
            background: #f5f5f5;
            border-radius: 8px;
        }
        .group-label {
            font-size: 0.72rem;
            font-weight: 600;
            color: #999;
            cursor: pointer;
            user-select: none;
        }
        .group-label:hover { color: #4a90d9; }
        .temp-group-label { color: #999; }
        .temp-group-label:hover { color: #4a90d9; }
        .group-label-wrap {
            position: absolute;
            top: 4px;
            left: 8px;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }
        .group-del-btn {
            display: none;
            width: 14px;
            height: 14px;
            line-height: 14px;
            text-align: center;
            font-size: 9px;
            background: #e53935;
            color: #fff;
            border-radius: 50%;
            cursor: pointer;
            border: none;
            padding: 0;
            flex-shrink: 0;
        }
        .group-label-wrap:hover .group-del-btn,
        .editing .group-del-btn { display: block; }
        .group-del-btn:hover { background: #b71c1c; }
        .group-order-num {
            display: inline-block;
            width: 18px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            font-size: 0.7rem;
            color: #999;
            background: #eee;
            border-radius: 3px;
            cursor: pointer;
            user-select: none;
            flex-shrink: 0;
        }
        .group-order-num:hover { background: #ddd; color: #4a90d9; }
        .group-order-input {
            font-size: 0.7rem !important;
            font-weight: 600;
            padding: 0 2px !important;
            border: none !important;
            border-bottom: 1px solid #4a90d9 !important;
            border-radius: 0 !important;
            outline: none;
            background: transparent;
            color: #4a90d9;
            text-align: center;
            line-height: 1;
            height: 1.2em;
            box-sizing: content-box;
            flex-shrink: 0;
        }
        .group-rename-input {
            font-size: 0.72rem !important;
            font-weight: 600;
            padding: 0 3px !important;
            border: none !important;
            border-bottom: 1px solid #4a90d9 !important;
            border-radius: 0 !important;
            outline: none;
            background: transparent;
            color: #4a90d9;
            line-height: 1;
            height: 1.2em;
            box-sizing: content-box;
        }
        .group-add-btn {
            padding: 2px 14px;
            font-size: 0.75rem;
            color: #bbb;
            background: #fff;
            border: 1px dashed #ddd;
            border-radius: 10px;
            cursor: pointer;
            line-height: 1.8;
        }
        .group-add-btn:hover { color: #4a90d9; border-color: #4a90d9; border-style: solid; }
        .edit-toggle-btn {
            padding: 2px 14px;
            font-size: 0.75rem;
            color: #bbb;
            background: #fff;
            border: 1px dashed #ddd;
            border-radius: 10px;
            cursor: pointer;
            line-height: 1.8;
        }
        .edit-toggle-btn:hover { color: #e53935; border-color: #e53935; border-style: solid; background: #fff5f5; }
        .group-add-input {
            width: 90px;
            padding: 2px 14px !important;
            font-size: 0.75rem !important;
            line-height: 1.8;
            color: #4a90d9;
            background: #fff;
            border: 1px solid #4a90d9 !important;
            border-radius: 10px !important;
            outline: none;
            box-sizing: border-box;
        }
        .group-add-input::placeholder { color: #4a90d9; }
        .tag-item {
            position: relative;
            display: inline-block;
        }
        .tag-btn {
            display: block;
            padding: 5px 12px;
            border: 1px solid #ccc;
            border-radius: 14px;
            background: #fff;
            cursor: pointer;
            font-size: 0.9rem;
            line-height: 1.4;
            user-select: none;
            transition: all 0.15s;
        }
        .tag-btn:hover {
            border-color: #999;
        }
        .tag-btn.selected {
            background: #4a90d9;
            color: #fff;
            border-color: #4a90d9;
        }
        .tag-order {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 16px;
            height: 16px;
            line-height: 16px;
            text-align: center;
            font-size: 10px;
            font-weight: 700;
            background: #1a5fa8;
            color: #fff;
            border-radius: 50%;
            pointer-events: none;
        }
        .tag-del-btn {
            display: none;
            position: absolute;
            top: -6px;
            right: -6px;
            width: 16px;
            height: 16px;
            line-height: 16px;
            text-align: center;
            font-size: 11px;
            background: #f44336;
            color: #fff;
            border-radius: 50%;
            cursor: pointer;
            border: none;
            padding: 0;
        }
        .tag-item:hover .tag-del-btn,
        .editing .tag-del-btn { display: block; }
        .tag-del-btn:hover { background: #b71c1c; }
        /* 选中时序号取代删除按钮 */
        .tag-item:has(.tag-btn.selected) .tag-del-btn { display: none !important; }
        /* 编辑模式下强制显示删除按钮，隐藏序号 */
        .editing .tag-item:has(.tag-btn.selected) .tag-del-btn { display: block !important; }
        .editing .tag-order { display: none; }
        .tag-add-input {
            width: 60px;
            padding: 5px 12px !important;
            font-size: 0.9rem !important;
            line-height: 1.4;
            border: 1px dashed #aaa !important;
            border-radius: 14px !important;
            outline: none;
            box-sizing: border-box;
            color: #999;
            background: transparent;
        }
        .tag-add-input:hover {
            border-color: #4a90d9 !important;
            border-style: solid !important;
            color: #4a90d9;
        }
        .tag-add-input:focus {
            border-color: #4a90d9 !important;
            border-style: solid !important;
            color: #333;
            width: 100px;
        }
        .note-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            margin-bottom: 4px;
        }
        .temp-tag-item {
            position: relative;
            display: inline-block;
        }
        .temp-tag-btn {
            display: block;
            padding: 5px 12px;
            border: 1px solid #ccc;
            border-radius: 14px;
            background: #fff;
            color: #555;
            cursor: pointer;
            font-size: 0.9rem;
            line-height: 1.4;
            user-select: none;
        }
        .temp-tag-btn:hover { border-color: #999; }
        .temp-tag-btn.selected {
            background: #4a90d9;
            color: #fff;
            border-color: #4a90d9;
        }
        .temp-del-btn {
            display: none;
            position: absolute;
            top: -6px;
            right: -6px;
            width: 16px;
            height: 16px;
            line-height: 16px;
            text-align: center;
            font-size: 11px;
            background: #e53935;
            color: #fff;
            border-radius: 50%;
            cursor: pointer;
            border: none;
            padding: 0;
        }
        .temp-tag-item:hover .temp-del-btn,
        .editing .temp-del-btn { display: block; }
        .temp-del-btn:hover { background: #b71c1c; }
        .temp-add-input {
            padding: 5px 12px !important;
            font-size: 0.9rem !important;
            line-height: 1.4;
            border: 1px dashed #aaa !important;
            border-radius: 14px !important;
            outline: none;
            box-sizing: border-box;
            color: #999;
            background: transparent;
            width: 60px;
        }
        .temp-add-input:hover {
            border-color: #4a90d9 !important;
            border-style: solid !important;
            color: #4a90d9;
        }
        .temp-add-input:focus {
            border-color: #4a90d9 !important;
            border-style: solid !important;
            color: #333;
            width: 100px;
        }
        .add-btn {
            padding: 5px 14px;
            font-size: 0.9rem;
            line-height: 1.4;
            border: none;
            background: #4a90d9;
            color: #fff;
            border-radius: 14px;
            cursor: pointer;
            flex-shrink: 0;
            margin-left: auto;
        }
        .add-btn:hover { background: #3a7bc8; }
        .submit-btn {
            display: block;
            margin: 0 0 8px auto;
            padding: 7px 20px;
            font-size: 0.95rem;
            font-weight: 500;
            border: none;
            background: #4a90d9;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
        }
        .submit-btn:hover { background: #3a7bc8; }
        .tag-label {
            display: inline-block;
            padding: 3px 10px;
            margin-right: 4px;
            background: #fff;
            color: #555;
            border: 1px solid #ccc;
            border-radius: 10px;
            font-size: 0.92rem;
        }
        .done .tag-label {
            background: #f5f5f5;
            color: #aaa;
            border-color: #e0e0e0;
        }
    </style>
</head>
<body>

<div class="tips">
    选择 tag → 点添加<br>
    单击 → 完成/恢复<br>
    双击 → 编辑<br>
    × → 删除<br>
    点分组名 → 改名<br>
    分组名 × → 删除分组
</div>

<div style="display:flex;justify-content:space-between;align-items:baseline;">
<h2 style="margin:0;">共享 Todo</h2>
<span id="todayDate" style="font-size:0.85rem;color:#999;"></span>
</div>

<div class="tag-bar" id="tagBar"></div>

<div class="tag-group note-row" id="tempRow"></div>

<button class="submit-btn" id="addBtn">＋ 添加为 Todo</button>

<div id="pendingSection">
    <div class="section-title">待办</div>
    <ul id="todoList"></ul>
</div>

<div id="doneSection" style="display:none;">
    <div class="section-title">已完成</div>
    <ul id="doneList" class="done-section"></ul>
</div>

<script>
    const todosData = <?php echo json_encode($todos); ?>;
    let todos = todosData;
    let groups = <?php echo json_encode($preset_tags ?: []); ?>; // [{name, tags}]
    let selectedTags = [];      // 仅用于成员判断（preset tags）
    let selectedTempTags = []; // 仅用于成员判断（temp tags）
    let selectionOrder = [];   // 全局选中顺序，统一追踪所有 tag
    let editingTags = false;   // 编辑模式：显示 × 删除按钮
    let tempTags = [];         // 临时 tag，不持久化
    const hasPassword = <?php echo json_encode($admin_password !== ''); ?>;
    let globalPw = '';

    function formatTime(ts) {
        if (!ts) return '';
        const d = new Date(ts * 1000);
        return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
    }

    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function renderTagBar() {
        const bar = document.getElementById('tagBar');
        if (editingTags) bar.classList.add('editing');
        else bar.classList.remove('editing');
        let html = '';

        // 每个分组一行
        groups.forEach(function(group, gi) {
            html += '<div class="tag-group" data-gi="' + gi + '">';
            html += '<span class="group-label-wrap">';
            html += '<span class="group-order-num" data-ordergi="' + gi + '">' + (gi + 1) + '</span>';
            html += '<span class="group-label" data-rengi="' + gi + '" title="点击改名">' + escapeHtml(group.name) + '</span>';
            html += '<button class="group-del-btn" type="button" data-delgi="' + gi + '">×</button>';
            html += '</span>';
            group.tags.forEach(function(tag) {
                const orderIdx = selectionOrder.indexOf(tag);
                const sel = orderIdx >= 0 ? ' selected' : '';
                const badge = orderIdx >= 0 ? '<span class="tag-order">' + (orderIdx + 1) + '</span>' : '';
                html += '<span class="tag-item">';
                html += '<span class="tag-btn' + sel + '" data-tag="' + escapeHtml(tag) + '">' + escapeHtml(tag) + '</span>';
                html += badge;
                html += '<button class="tag-del-btn" type="button" data-deltag="' + escapeHtml(tag) + '" data-gi="' + gi + '">×</button>';
                html += '</span>';
            });
            html += '<input type="text" class="tag-add-input" data-gi="' + gi + '" placeholder="+ tag">';
            html += '</div>';
        });

        // 在所有分组外面、下方居中加编辑切换和"+ 分组"按钮
        html += '<div style="text-align:center;margin-top:2px;display:flex;justify-content:center;gap:8px;">';
        html += '<button class="edit-toggle-btn" id="editToggleBtn">' + (editingTags ? '✓ 完成' : '✎ 编辑') + '</button>';
        html += '<button class="group-add-btn" id="groupAddBtn">＋ 分组</button>';
        html += '</div>';
        bar.innerHTML = html;

        // 绑定各组 +tag 输入
        bar.querySelectorAll('.tag-add-input[data-gi]').forEach(function(input) {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var tag = this.value.trim();
                    var gi = parseInt(this.dataset.gi);
                    if (tag) addPresetTag(gi, tag);
                    this.value = '';
                }
            });
        });

        // 绑定编辑模式切换
        document.getElementById('editToggleBtn').addEventListener('click', function() {
            editingTags = !editingTags;
            renderTagBar();
        });

        // 绑定新增分组按钮 - 点击变成内联输入框
        document.getElementById('groupAddBtn').addEventListener('click', function() {
            var btn = this;
            var input = document.createElement('input');
            input.type = 'text';
            input.className = 'group-add-input';
            input.placeholder = '＋ 分组';
            input.style.cssText = '';
            btn.replaceWith(input);
            input.focus();
            function confirm() {
                var name = input.value.trim();
                if (name) addGroup(name);
                else renderTagBar();
            }
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); confirm(); }
                if (e.key === 'Escape') renderTagBar();
            });
            input.addEventListener('blur', confirm);
        });

        // 渲染临时 tag 行（和永久分组同样的框框样式）
        var row = document.getElementById('tempRow');
        var tempHtml = '<span class="group-label-wrap"><span class="group-label temp-group-label">临时</span></span>';
        for (const tag of tempTags) {
            const orderIdx = selectionOrder.indexOf(tag);
            const sel = orderIdx >= 0 ? ' selected' : '';
            const badge = orderIdx >= 0 ? '<span class="tag-order">' + (orderIdx + 1) + '</span>' : '';
            tempHtml += '<span class="temp-tag-item">';
            tempHtml += '<span class="temp-tag-btn' + sel + '" data-temptag="' + escapeHtml(tag) + '">' + escapeHtml(tag) + '</span>';
            tempHtml += badge;
            tempHtml += '<button class="temp-del-btn" type="button" data-deltmptag="' + escapeHtml(tag) + '">×</button>';
            tempHtml += '</span>';
        }
        tempHtml += '<input type="text" class="temp-add-input" id="tempAddInput" placeholder="+ tag">';
        row.innerHTML = tempHtml;

        // 绑定新增临时 tag 输入
        document.getElementById('tempAddInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var tag = this.value.trim();
                if (tag) {
                    if (!tempTags.includes(tag)) tempTags.push(tag);
                    if (!selectedTempTags.includes(tag)) {
                        selectedTempTags.push(tag);
                        selectionOrder.push(tag);
                    }
                    this.value = '';
                    renderTagBar();
                }
            }
        });

        // 绑定添加按钮（只需初始化时绑定一次，见下方）
    }

    function renderTodoContent(todo) {
        let html = '';
        const tags = todo.tags || [];
        for (const tag of tags) {
            html += `<span class="tag-label">${escapeHtml(tag)}</span>`;
        }
        if (todo.text) {
            html += ` ${escapeHtml(todo.text)}`;
        }
        return html;
    }

    function render() {
        const todoList = document.getElementById('todoList');
        const doneList = document.getElementById('doneList');
        const doneSection = document.getElementById('doneSection');

        const pending = [];
        const done = [];
        for (let i = 0; i < todos.length; i++) {
            if (todos[i].done) {
                done.push({ ...todos[i], idx: i });
            } else {
                pending.push({ ...todos[i], idx: i });
            }
        }

        if (pending.length === 0) {
            todoList.innerHTML = '<li><small>选择 tag 后按回车添加任务</small></li>';
        } else {
            let html = '';
            for (const t of pending) {
                html += `
                    <li>
                        <div class="todo-text" data-idx="${t.idx}">${renderTodoContent(t)}</div>
                        <div class="timestamp">创建于 ${formatTime(t.created_at)}</div>
                        <span class="delete-link" data-idx="${t.idx}">×</span>
                    </li>
                `;
            }
            todoList.innerHTML = html;
        }

        if (done.length === 0) {
            doneSection.style.display = 'none';
        } else {
            doneSection.style.display = '';
            let html = '';
            for (const t of done) {
                const timeStr = t.done_at ? `完成于 ${formatTime(t.done_at)}` : formatTime(t.created_at);
                html += `
                    <li>
                        <div class="todo-text done" data-idx="${t.idx}">${renderTodoContent(t)}</div>
                        <div class="timestamp">${timeStr}</div>
                        <span class="delete-link" data-idx="${t.idx}">×</span>
                    </li>
                `;
            }
            doneList.innerHTML = html;
        }

        document.querySelectorAll('.todo-text').forEach(el => {
            el.addEventListener('click', handleToggle);
            el.addEventListener('dblclick', handleEdit);
        });
        document.querySelectorAll('.delete-link').forEach(el => {
            el.addEventListener('click', handleDelete);
        });
    }

    async function sendRequest(payload) {
        if (hasPassword && !globalPw) {
            const pw = prompt('请输入密码：');
            if (pw === null) return false;
            globalPw = pw;
        }
        if (hasPassword) payload.pw = globalPw;

        try {
            const res = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.error) {
                alert('操作失败：' + data.error);
                if (data.error.includes('密码')) globalPw = '';
                return null;
            }
            return data;
        } catch (e) {
            alert('网络错误：' + e.message);
            return null;
        }
    }

    let clickTimer = null;

    async function handleToggle(e) {
        e.stopPropagation();
        if (clickTimer) clearTimeout(clickTimer);
        const el = this;
        clickTimer = setTimeout(async () => {
            const idx = parseInt(el.dataset.idx);
            const oldDone = todos[idx].done;
            const oldDoneAt = todos[idx].done_at;
            todos[idx].done = !oldDone;
            todos[idx].done_at = todos[idx].done ? Math.floor(Date.now() / 1000) : null;
            render();
            const ok = await sendRequest({ action: 'toggle', idx });
            if (!ok) {
                todos[idx].done = oldDone;
                todos[idx].done_at = oldDoneAt;
                render();
            }
        }, 200);
    }

    function handleEdit(e) {
        e.stopPropagation();
        if (clickTimer) clearTimeout(clickTimer);
        const el = this;
        const idx = parseInt(el.dataset.idx);
        const oldTags = todos[idx].tags || [];
        const oldText = todos[idx].text || '';

        // 替换为输入框，显示当前内容
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'edit-input';
        input.value = oldTags.map(t => `[${t}]`).join(' ') + (oldText ? ' ' + oldText : '');
        el.replaceWith(input);
        input.focus();
        input.select();

        let saved = false;
        async function save() {
            if (saved) return;
            saved = true;
            const val = input.value.trim();
            // 解析 [tag] 格式
            const tagMatches = val.match(/\[([^\]]+)\]/g) || [];
            const newTags = tagMatches.map(m => m.slice(1, -1));
            const newText = val.replace(/\[([^\]]+)\]/g, '').trim();

            if (newTags.length === 0 && newText === '') {
                render();
                return;
            }

            todos[idx].tags = newTags;
            todos[idx].text = newText;
            render();
            const ok = await sendRequest({ action: 'edit', idx, tags: newTags, text: newText });
            if (!ok) {
                todos[idx].tags = oldTags;
                todos[idx].text = oldText;
                render();
            }
        }

        input.addEventListener('keydown', function(ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); save(); }
            if (ev.key === 'Escape') { saved = true; render(); }
        });
        input.addEventListener('blur', save);
    }

    async function handleDelete(e) {
        e.stopPropagation();
        const idx = parseInt(this.dataset.idx);
        const deleted = todos[idx];
        todos.splice(idx, 1);
        render();
        const ok = await sendRequest({ action: 'delete', idx });
        if (!ok) {
            todos.splice(idx, 0, deleted);
            render();
        }
    }

    async function addTask() {
        if (selectionOrder.length === 0) return;
        const allTags = [...selectionOrder];

        const newTodo = {
            tags: allTags,
            text: '',
            done: false,
            created_at: Math.floor(Date.now() / 1000),
            done_at: null
        };
        todos.unshift(newTodo);
        render();
        selectedTags = [];
        selectedTempTags = [];
        selectionOrder = [];
        tempTags = [];
        renderTagBar();

        const ok = await sendRequest({ action: 'add', tags: allTags, text: '' });
        if (!ok) {
            todos.shift();
            render();
            alert('添加失败，请重试');
        }
    }

    async function addPresetTag(gi, tag) {
        if (groups[gi].tags.includes(tag)) return;
        groups[gi].tags.push(tag);
        renderTagBar();
        const ok = await sendRequest({ action: 'add_preset_tag', gi, tag });
        if (!ok) { groups[gi].tags.pop(); renderTagBar(); }
    }

    async function deletePresetTag(gi, tag) {
        const old = groups[gi].tags.slice();
        groups[gi].tags = groups[gi].tags.filter(function(t) { return t !== tag; });
        selectedTags = selectedTags.filter(function(t) { return t !== tag; });
        selectionOrder = selectionOrder.filter(function(t) { return t !== tag; });
        renderTagBar();
        const ok = await sendRequest({ action: 'delete_preset_tag', gi, tag });
        if (!ok) { groups[gi].tags = old; renderTagBar(); }
    }

    async function addGroup(name) {
        groups.push({ name: name, tags: [] });
        renderTagBar();
        const ok = await sendRequest({ action: 'add_group', name });
        if (!ok) { groups.pop(); renderTagBar(); }
    }

    async function deleteGroup(gi) {
        const old = groups.splice(gi, 1)[0];
        renderTagBar();
        const ok = await sendRequest({ action: 'delete_group', gi });
        if (!ok) { groups.splice(gi, 0, old); renderTagBar(); }
    }

    async function moveGroupTo(gi, to) {
        if (to < 0 || to >= groups.length || gi === to) return;
        // 本地移动
        const group = groups.splice(gi, 1)[0];
        groups.splice(to, 0, group);
        renderTagBar();
        const ok = await sendRequest({ action: 'move_group_to', gi, to });
        if (!ok) {
            // 失败回滚
            const back = groups.splice(to, 1)[0];
            groups.splice(gi, 0, back);
            renderTagBar();
        }
    }

    async function renameGroup(gi, name) {
        const old = groups[gi].name;
        groups[gi].name = name;
        renderTagBar();
        const ok = await sendRequest({ action: 'rename_group', gi, name });
        if (!ok) { groups[gi].name = old; renderTagBar(); }
    }

    document.getElementById('newTask') && document.getElementById('newTask').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); addTask(); }
    });

    // 事件委托：处理 tag 栏的点击（选中/取消、删除 tag、删除分组）
    document.getElementById('tagBar').addEventListener('click', function(e) {
        var target = e.target;
        var tag, gi;
        // 点击 tag 按钮（编辑模式=删除，普通模式=选中/取消）
        if (target.classList.contains('tag-btn')) {
            tag = target.dataset.tag;
            if (editingTags) {
                // 编辑模式下点击 tag = 删除
                var parent = target.closest('.tag-group');
                gi = parseInt(parent ? parent.dataset.gi : 0);
                deletePresetTag(gi, tag);
            } else {
                var idx = selectedTags.indexOf(tag);
                if (idx >= 0) {
                    selectedTags.splice(idx, 1);
                    selectionOrder = selectionOrder.filter(function(t) { return t !== tag; });
                } else {
                    selectedTags.push(tag);
                    selectionOrder.push(tag);
                }
                renderTagBar();
            }
            return;
        }
        // 点击分组序号 → 编辑排序
        if (target.classList.contains('group-order-num') && target.dataset.ordergi !== undefined) {
            var orderGi = parseInt(target.dataset.ordergi);
            var curNum = orderGi + 1;
            var input = document.createElement('input');
            input.type = 'text';
            input.className = 'group-order-input';
            input.value = curNum;
            input.style.width = '24px';
            target.replaceWith(input);
            input.focus();
            input.select();
            var saved = false;
            function saveOrder() {
                if (saved) return; saved = true;
                var val = parseInt(input.value.trim());
                if (!isNaN(val) && val >= 1 && val <= groups.length && val !== curNum) {
                    moveGroupTo(orderGi, val - 1);
                } else {
                    renderTagBar();
                }
            }
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); saveOrder(); }
                if (e.key === 'Escape') { saved = true; renderTagBar(); }
            });
            input.addEventListener('blur', saveOrder);
            return;
        }
        // 点击删除 tag 按钮
        if (target.classList.contains('tag-del-btn')) {
            gi = parseInt(target.dataset.gi);
            tag = target.dataset.deltag;
            deletePresetTag(gi, tag);
            return;
        }
        // 点击分组标签改名
        if (target.classList.contains('group-label') && target.dataset.rengi !== undefined) {
            var gi = parseInt(target.dataset.rengi);
            var oldName = groups[gi].name;
            var input = document.createElement('input');
            input.type = 'text';
            input.className = 'group-rename-input';
            input.value = oldName;
            input.style.width = Math.max(40, oldName.length * 9) + 'px';
            target.replaceWith(input);
            input.focus();
            input.select();
            var saved = false;
            function saveRename() {
                if (saved) return; saved = true;
                var name = input.value.trim();
                if (name && name !== oldName) renameGroup(gi, name);
                else renderTagBar();
            }
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); saveRename(); }
                if (e.key === 'Escape') { saved = true; renderTagBar(); }
            });
            input.addEventListener('blur', saveRename);
            return;
        }
        // 点击分组删除按钮
        if (target.classList.contains('group-del-btn') && target.dataset.delgi !== undefined) {
            gi = parseInt(target.dataset.delgi);
            var groupName = groups[gi].name;
            if (groups[gi].tags.length === 0 || confirm('删除分组「' + groupName + '」及其所有 tag？')) {
                deleteGroup(gi);
            }
        }
    });

    // 事件委托：临时 tag 行（编辑模式=删除，普通模式=选中/取消）
    document.getElementById('tempRow').addEventListener('click', function(e) {
        var target = e.target;
        var tag;
        if (target.classList.contains('temp-tag-btn')) {
            tag = target.dataset.temptag;
            if (editingTags) {
                // 编辑模式下点击 = 删除临时 tag
                tempTags = tempTags.filter(function(t) { return t !== tag; });
                selectedTempTags = selectedTempTags.filter(function(t) { return t !== tag; });
                selectionOrder = selectionOrder.filter(function(t) { return t !== tag; });
                renderTagBar();
            } else {
                var tidx = selectedTempTags.indexOf(tag);
                if (tidx >= 0) {
                    selectedTempTags.splice(tidx, 1);
                    selectionOrder = selectionOrder.filter(function(t) { return t !== tag; });
                } else {
                    selectedTempTags.push(tag);
                    selectionOrder.push(tag);
                }
                renderTagBar();
            }
            return;
        }
        if (target.classList.contains('temp-del-btn')) {
            tag = target.dataset.deltmptag;
            tempTags = tempTags.filter(function(t) { return t !== tag; });
            selectedTempTags = selectedTempTags.filter(function(t) { return t !== tag; });
            selectionOrder = selectionOrder.filter(function(t) { return t !== tag; });
            renderTagBar();
        }
    });

    document.getElementById('addBtn').addEventListener('click', addTask);

    try {
        renderTagBar();
        render();
        // 显示当天日期
        var now = new Date();
        var y = now.getFullYear();
        var m = String(now.getMonth() + 1).padStart(2, '0');
        var d = String(now.getDate()).padStart(2, '0');
        var weekdays = ['日', '一', '二', '三', '四', '五', '六'];
        var w = weekdays[now.getDay()];
        document.getElementById('todayDate').textContent = y + '-' + m + '-' + d + ' 周' + w;
    } catch (e) {
        console.error('初始化错误:', e);
        document.body.insertAdjacentHTML('afterbegin', '<div style="background:#fcc;padding:8px;border:1px solid red;margin-bottom:8px"><strong>页面错误:</strong> ' + e.message + '</div>');
    }
</script>
</body>
</html>
