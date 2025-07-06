<?php
// 运动读书打卡学分统计系统 - 完整版

// 配置
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');
define('DATA_FILE', __DIR__ . '/data.txt');
define('NAMES_FILE', __DIR__ . '/names.txt');

// 初始化数据文件
if (!file_exists(DATA_FILE)) file_put_contents(DATA_FILE, '');
if (!file_exists(NAMES_FILE)) file_put_contents(NAMES_FILE, '');

// 会话管理
session_start();

// 工具函数
function isAdmin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function getWeekday($date) {
    $weekday = ['日', '一', '二', '三', '四', '五', '六'];
    return '周' . $weekday[date('w', strtotime($date))];
}

function getNames() {
    return file_exists(NAMES_FILE) ? explode("\n", trim(file_get_contents(NAMES_FILE))) : [];
}

function addName($name) {
    $names = getNames();
    if (!in_array(trim($name), $names)) {
        $names[] = trim($name);
        file_put_contents(NAMES_FILE, implode("\n", $names));
    }
}

function getAllData() {
    if (!file_exists(DATA_FILE) || !filesize(DATA_FILE)) return [];
    $lines = file(DATA_FILE, FILE_IGNORE_NEW_LINES);
    return array_map('json_decode', $lines, array_fill(0, count($lines), true));
}

// 处理获取星期几的请求
if (isset($_GET['get_weekday'])) {
    echo getWeekday($_GET['get_weekday']);
    exit;
}

// 登录/登出
if (isset($_POST['login'])) {
    if ($_POST['username'] === ADMIN_USERNAME && $_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: index.php');
        exit;
    }
    $loginError = '用户名或密码错误';
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 数据管理
if (isAdmin()) {
    // 添加记录
    if (isset($_POST['add'])) {
        // 处理姓名输入
        $selectedName = isset($_POST['name']) ? trim($_POST['name']) : '';
        $newName = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';
        
        if (!empty($newName)) {
            $name = $newName;
            addName($name);
        } elseif (!empty($selectedName)) {
            $name = $selectedName;
        } else {
            die('姓名不能为空');
        }
        
        $data = [
            'id' => uniqid(),
            'team' => $_POST['team'],
            'name' => $name,
            'date' => $_POST['date'],
            'reading' => isset($_POST['reading']) ? 1 : 0,
            'sharing' => isset($_POST['sharing']) ? 3 : 0,
            'sharingScore' => isset($_POST['sharing_score']) ? (int)$_POST['sharing_score'] : 0,
            'exercise' => isset($_POST['exercise']) ? 1 : 0
        ];
        
        // 验证优秀分享必须有分数
        if ($data['sharing'] > 0 && $data['sharingScore'] <= 0) {
            die('被评为优秀分享时必须选择分数');
        }
        
        file_put_contents(DATA_FILE, json_encode($data) . "\n", FILE_APPEND);
        header('Location: index.php');
        exit;
    }

    // 编辑记录
    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $lines = file(DATA_FILE, FILE_IGNORE_NEW_LINES);
        $newLines = [];
        
        // 处理姓名输入
        $selectedName = isset($_POST['name']) ? trim($_POST['name']) : '';
        $newName = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';
        
        if (!empty($newName)) {
            $name = $newName;
            addName($name);
        } elseif (!empty($selectedName)) {
            $name = $selectedName;
        } else {
            die('姓名不能为空');
        }
        
        foreach ($lines as $line) {
            $record = json_decode($line, true);
            if ($record['id'] === $id) {
                $record = [
                    'id' => $id,
                    'team' => $_POST['team'],
                    'name' => $name,
                    'date' => $_POST['date'],
                    'reading' => isset($_POST['reading']) ? 1 : 0,
                    'sharing' => isset($_POST['sharing']) ? 3 : 0,
                    'sharingScore' => isset($_POST['sharing_score']) ? (int)$_POST['sharing_score'] : 0,
                    'exercise' => isset($_POST['exercise']) ? 1 : 0
                ];
                
                // 验证优秀分享必须有分数
                if ($record['sharing'] > 0 && $record['sharingScore'] <= 0) {
                    die('被评为优秀分享时必须选择分数');
                }
            }
            $newLines[] = json_encode($record);
        }
        
        file_put_contents(DATA_FILE, implode("\n", $newLines));
        header('Location: index.php');
        exit;
    }

    // 删除记录
    if (isset($_GET['delete'])) {
        $id = $_GET['delete'];
        $lines = file(DATA_FILE, FILE_IGNORE_NEW_LINES);
        
        $newLines = array_filter($lines, function($line) use ($id) {
            return json_decode($line, true)['id'] !== $id;
        });
        file_put_contents(DATA_FILE, implode("\n", $newLines));
        header('Location: index.php');
        exit;
    }
}

// 获取数据并排序
$data = getAllData();
usort($data, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$recentData = array_slice($data, 0, 20);
$currentDate = date('Y-m-d');

// 准备编辑数据
$editData = null;
if (isset($_GET['edit']) && isAdmin()) {
    $editId = $_GET['edit'];
    $editData = array_filter($data, function($record) use ($editId) {
        return $record['id'] === $editId;
    });
    $editData = array_shift($editData);
}

// 统计数据函数
function calculateStats($data) {
    $stats = [];
    
    foreach ($data as $record) {
        if (!isset($record['name'], $record['team'])) continue;
        
        if (!isset($stats[$record['name']])) {
            $stats[$record['name']] = [
                'team' => $record['team'],
                'reading' => 0,
                'sharing' => 0,
                'exercise' => 0,
                'exerciseCount' => 0,
                'records' => []
            ];
        }
        
        $stats[$record['name']]['reading'] += $record['reading'] ?? 0;
        $stats[$record['name']]['sharing'] += $record['sharing'] ?? 0;
        $stats[$record['name']]['exerciseCount'] += $record['exercise'] ?? 0;
        $stats[$record['name']]['records'][] = $record;
    }
    
    // 计算运动积分
    foreach ($stats as &$stat) {
        $weeks = [];
        foreach ($stat['records'] as $record) {
            if (!empty($record['exercise']) && !empty($record['date'])) {
                $week = date('Y-W', strtotime($record['date']));
                $weeks[$week] = ($weeks[$week] ?? 0) + 1;
            }
        }
        
        foreach ($weeks as $count) {
            if ($count >= 3) $stat['exercise'] += 3;
        }
        
        $stat['total'] = $stat['reading'] + $stat['sharing'] + $stat['exercise'];
    }
    
    return $stats;
}

// 按时间段过滤数据
function filterByPeriod($data, $period, $date = null) {
    $date = $date ?: date('Y-m-d');
    $validData = array_filter($data, function($record) {
        return !empty($record['date']) && strtotime($record['date']) !== false;
    });
    
    switch ($period) {
        case 'day':
            $targetDate = $date;
            return array_filter($validData, function($record) use ($targetDate) {
                return $record['date'] === $targetDate;
            });
            
        case 'week':
            $start = date('Y-m-d', strtotime('last Monday', strtotime($date)));
            $end = date('Y-m-d', strtotime('next Sunday', strtotime($date)));
            return array_filter($validData, function($record) use ($start, $end) {
                return $record['date'] >= $start && $record['date'] <= $end;
            });
            
        case 'month':
            $year = date('Y', strtotime($date));
            $month = date('m', strtotime($date));
            $days = date('t', strtotime("$year-$month-01"));
            $start = "$year-$month-01";
            $end = "$year-$month-$days";
            return array_filter($validData, function($record) use ($start, $end) {
                return $record['date'] >= $start && $record['date'] <= $end;
            });
            
        default:
            return $validData;
    }
}

// 计算不同时间段的统计
$dayStats = calculateStats(filterByPeriod($data, 'day', $currentDate));
$weekStats = calculateStats(filterByPeriod($data, 'week', $currentDate));
$monthStats = calculateStats(filterByPeriod($data, 'month', $currentDate));
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>运动读书打卡学分统计</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
        h1 { text-align: center; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: center; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .container { margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, select { padding: 8px; width: 100%; box-sizing: border-box; }
        button { padding: 10px 15px; background-color: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #45a049; }
        .btn-delete { background-color: #f44336; }
        .btn-delete:hover { background-color: #d32f2f; }
        .btn-edit { background-color: #2196F3; }
        .btn-edit:hover { background-color: #0b7dda; }
        .login-form { max-width: 400px; margin: 0 auto; }
        .error { color: red; }
        .period-selector button { margin-right: 5px; }
        .stats-container { display: none; }
        .stats-container.active { display: block; }
    </style>
</head>
<body>
    <h1>运动读书打卡学分统计</h1>
    
    <?php if (!isAdmin()): ?>
        <div class="login-form">
            <h2>管理员登录</h2>
            <?php if (isset($loginError)): ?>
                <p class="error"><?php echo $loginError; ?></p>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="username">用户名:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">密码:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" name="login">登录</button>
            </form>
        </div>
    <?php else: ?>
        <div style="text-align: right; margin-bottom: 10px;">
            <a href="?logout=1">退出登录</a>
        </div>
        
        <!-- 数据录入/编辑表单 -->
        <div class="container">
            <h2><?php echo $editData ? '编辑打卡记录' : '添加打卡记录'; ?></h2>
            <form method="post">
                <?php if ($editData): ?>
                    <input type="hidden" name="id" value="<?php echo $editData['id']; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="team">团队:</label>
                    <select id="team" name="team" required>
                        <option value="乐观组" <?php echo ($editData && $editData['team'] === '乐观组') ? 'selected' : ''; ?>>乐观组</option>
                        <option value="利他组" <?php echo ($editData && $editData['team'] === '利他组') ? 'selected' : ''; ?>>利他组</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="name">姓名:</label>
                    <select id="name" name="name">
                        <option value="">请选择或输入姓名</option>
                        <?php foreach (getNames() as $name): ?>
                            <option value="<?php echo htmlspecialchars($name); ?>" <?php echo ($editData && $editData['name'] === $name) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="new_name" name="new_name" placeholder="输入新姓名">
                </div>
                <div class="form-group">
                    <label for="date">日期:</label>
                    <input type="date" id="date" name="date" value="<?php echo $editData ? $editData['date'] : date('Y-m-d'); ?>" required>
                    <span id="weekday"><?php echo $editData ? getWeekday($editData['date']) : getWeekday(date('Y-m-d')); ?></span>
                </div>
                <div class="form-group">
                    <label>读书点评:</label>
                    <input type="checkbox" id="reading" name="reading" <?php echo ($editData && $editData['reading'] == 1) ? 'checked' : ''; ?>>
                    <label for="reading">参与 (1分)</label>
                </div>
                <div class="form-group">
                    <label>优秀分享:</label>
                    <input type="checkbox" id="sharing" name="sharing" <?php echo ($editData && $editData['sharing'] > 0) ? 'checked' : ''; ?>>
                    <label for="sharing">被评为优秀分享 (3分)</label>
                    <select id="sharing_score" name="sharing_score" <?php echo ($editData && $editData['sharing'] > 0) ? '' : 'disabled'; ?>>
                        <option value="0">请选择分数</option>
                        <option value="90" <?php echo ($editData && $editData['sharingScore'] == 90) ? 'selected' : ''; ?>>90</option>
                        <option value="95" <?php echo ($editData && $editData['sharingScore'] == 95) ? 'selected' : ''; ?>>95</option>
                        <option value="100" <?php echo ($editData && $editData['sharingScore'] == 100) ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>健康运动:</label>
                    <input type="checkbox" id="exercise" name="exercise" <?php echo ($editData && $editData['exercise'] == 1) ? 'checked' : ''; ?>>
                    <label for="exercise">参与 (每周至少3次自动得3分，不满3次不计分)</label>
                </div>
                <button type="submit" name="<?php echo $editData ? 'edit' : 'add'; ?>"><?php echo $editData ? '保存修改' : '添加记录'; ?></button>
            </form>
        </div>
    <?php endif; ?>
    
    <!-- 最近记录 -->
    <div class="container">
        <h2>最近记录</h2>
        <table>
            <tr>
                <th>日期</th>
                <th>团队</th>
                <th>姓名</th>
                <th>读书点评</th>
                <th>优秀分享</th>
                <th>优秀分享得分</th>
                <th>健康运动</th>
                <?php if (isAdmin()): ?>
                    <th>操作</th>
                <?php endif; ?>
            </tr>
            <?php foreach ($recentData as $record): ?>
                <tr>
                    <td><?php echo $record['date'] . ' ' . getWeekday($record['date']); ?></td>
                    <td><?php echo $record['team']; ?></td>
                    <td><?php echo $record['name']; ?></td>
                    <td><?php echo $record['reading'] ? '1分' : '0分'; ?></td>
                    <td><?php echo $record['sharing'] ? '3分' : '0分'; ?></td>
                    <td><?php echo $record['sharingScore'] ?: '-'; ?></td>
                    <td><?php echo $record['exercise'] ? '记录' : '未记录'; ?></td>
                    <?php if (isAdmin()): ?>
                        <td>
                            <a href="?edit=<?php echo $record['id']; ?>" class="btn-edit">编辑</a>
                            <a href="?delete=<?php echo $record['id']; ?>" class="btn-delete" onclick="return confirm('确定要删除这条记录吗？')">删除</a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <!-- 统计数据 -->
    <div class="container">
        <h2>统计数据</h2>
        
        <div class="period-selector">
            <button onclick="showStats('day')">当天</button>
            <button onclick="showStats('week')">本周</button>
            <button onclick="showStats('month')" class="active">本月</button>
        </div>
        
        <!-- 个人统计 -->
        <div id="day-stats" class="stats-container">
            <h3>当天统计 (<?php echo $currentDate . ' ' . getWeekday($currentDate); ?>)</h3>
            <table>
                <tr>
                    <th>姓名</th>
                    <th>团队</th>
                    <th>读书点评</th>
                    <th>优秀分享</th>
                    <th>健康运动</th>
                    <th>总积分</th>
                </tr>
                <?php foreach ($dayStats as $name => $stats): ?>
                    <tr>
                        <td><?php echo $name; ?></td>
                        <td><?php echo $stats['team']; ?></td>
                        <td><?php echo $stats['reading']; ?></td>
                        <td><?php echo $stats['sharing']; ?></td>
                        <td><?php echo $stats['exercise']; ?></td>
                        <td><?php echo $stats['total']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div id="week-stats" class="stats-container" style="display: none;">
            <h3>本周统计</h3>
            <table>
                <tr>
                    <th>姓名</th>
                    <th>团队</th>
                    <th>读书点评</th>
                    <th>优秀分享</th>
                    <th>健康运动</th>
                    <th>总积分</th>
                </tr>
                <?php foreach ($weekStats as $name => $stats): ?>
                    <tr>
                        <td><?php echo $name; ?></td>
                        <td><?php echo $stats['team']; ?></td>
                        <td><?php echo $stats['reading']; ?></td>
                        <td><?php echo $stats['sharing']; ?></td>
                        <td><?php echo $stats['exercise']; ?></td>
                        <td><?php echo $stats['total']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div id="month-stats" class="stats-container" style="display: none;">
            <h3>本月统计</h3>
            <table>
                <tr>
                    <th>姓名</th>
                    <th>团队</th>
                    <th>读书点评</th>
                    <th>优秀分享</th>
                    <th>健康运动</th>
                    <th>总积分</th>
                </tr>
                <?php foreach ($monthStats as $name => $stats): ?>
                    <tr>
                        <td><?php echo $name; ?></td>
                        <td><?php echo $stats['team']; ?></td>
                        <td><?php echo $stats['reading']; ?></td>
                        <td><?php echo $stats['sharing']; ?></td>
                        <td><?php echo $stats['exercise']; ?></td>
                        <td><?php echo $stats['total']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <script>
        // 显示指定时间段的统计数据
        function showStats(period) {
            document.querySelectorAll('.stats-container').forEach(el => {
                el.style.display = 'none';
            });
            document.querySelectorAll('.period-selector button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            const statsEl = document.getElementById(`${period}-stats`);
            if (statsEl) {
                statsEl.style.display = 'block';
            }
            
            const activeBtn = Array.from(document.querySelectorAll('.period-selector button')).find(btn => 
                btn.textContent.includes(period === 'day' ? '当天' : 
                    period === 'week' ? '本周' : '本月')
            );
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
        }
        
        // 日期选择器变更时更新星期几
        document.getElementById('date').addEventListener('change', function() {
            const weekdayEl = document.getElementById('weekday');
            if (weekdayEl) {
                const xhr = new XMLHttpRequest();
                xhr.open('GET', '?get_weekday=' + this.value, true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            weekdayEl.textContent = xhr.responseText;
                        } else {
                            weekdayEl.textContent = '无效日期';
                        }
                    }
                };
                xhr.send();
            }
        });
        
        // 优秀分享复选框变更时启用/禁用分数选择
        document.getElementById('sharing').addEventListener('change', function() {
            const sharingScore = document.getElementById('sharing_score');
            if (sharingScore) {
                sharingScore.disabled = !this.checked;
                if (!this.checked) {
                    sharingScore.value = 0;
                }
            }
        });
        
        // 新姓名输入处理
        document.getElementById('name').addEventListener('change', function() {
            const newName = document.getElementById('new_name');
            if (newName) {
                if (this.value === '') {
                    newName.disabled = false;
                } else {
                    newName.disabled = true;
                    newName.value = '';
                }
            }
        });
        
        // 表单提交前验证
        document.querySelector('form').addEventListener('submit', function(e) {
            const nameSelect = document.getElementById('name');
            const newName = document.getElementById('new_name');
            const sharingCheckbox = document.getElementById('sharing');
            const sharingScore = document.getElementById('sharing_score');
            
            // 允许从下拉选择或手工输入
            if ((!nameSelect || nameSelect.value === '') && (!newName || newName.value === '')) {
                alert('请选择或输入姓名');
                e.preventDefault();
                return false;
            }
            
            // 优秀分享勾选后必须选择分数
            if (sharingCheckbox.checked && (!sharingScore || sharingScore.value <= 0)) {
                alert('被评为优秀分享时必须选择分数');
                e.preventDefault();
                return false;
            }
        });
        
        // 初始化显示当前激活的统计面板
        document.addEventListener('DOMContentLoaded', function() {
            showStats('month');
        });
    </script>
</body>
</html>    
