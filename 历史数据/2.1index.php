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

// 检查当天是否已有同名记录（添加时使用）
function checkDuplicateRecord($name, $date, $excludeId = null) {
    $data = getAllData();
    foreach ($data as $record) {
        if (isset($record['name'], $record['date']) && 
            $record['name'] === $name && 
            $record['date'] === $date &&
            ($excludeId === null || $record['id'] !== $excludeId)) {
            return true;
        }
    }
    return false;
}

// 安全保存所有数据到文件
function saveAllData($data) {
    $tempFile = DATA_FILE . '.tmp';
    $fp = fopen($tempFile, 'w');
    if ($fp) {
        foreach ($data as $record) {
            fwrite($fp, json_encode($record) . "\n");
        }
        fclose($fp);
        rename($tempFile, DATA_FILE);
        return true;
    }
    return false;
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
        
        // 检查当天是否已有同名记录
        if (checkDuplicateRecord($name, $_POST['date'])) {
            die('当天已存在该用户的记录，不能重复添加');
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
        
        $allData = getAllData();
        $allData[] = $data;
        if (saveAllData($allData)) {
            header('Location: index.php');
            exit;
        } else {
            die('保存数据失败');
        }
    }

    // 编辑记录
    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $allData = getAllData();
        $recordFound = false;
        
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
        
        // 检查当天是否已有同名记录（排除当前编辑的记录）
        if (checkDuplicateRecord($name, $_POST['date'], $id)) {
            die('当天已存在该用户的记录，不能重复添加');
        }
        
        foreach ($allData as &$record) {
            if ($record['id'] === $id) {
                $recordFound = true;
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
                break;
            }
        }
        
        if (!$recordFound) {
            die('要编辑的记录不存在');
        }
        
        if (saveAllData($allData)) {
            header('Location: index.php');
            exit;
        } else {
            die('保存数据失败');
        }
    }

    // 删除记录
    if (isset($_GET['delete'])) {
        $id = $_GET['delete'];
        $allData = getAllData();
        
        $newData = array_filter($allData, function($record) use ($id) {
            return $record['id'] !== $id;
        });
        
        if (saveAllData($newData)) {
            header('Location: index.php');
            exit;
        } else {
            die('删除数据失败');
        }
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

// 获取时间段函数 - 返回开始和结束日期
function getPeriodRange($period, $date = null) {
    $date = $date ?: date('Y-m-d');
    
    switch ($period) {
        case 'day':
            return [
                'start' => $date,
                'end' => $date,
                'format' => 'Y-m-d'
            ];
            
        case 'week':
            $start = date('Y-m-d', strtotime('last Monday', strtotime($date)));
            $end = date('Y-m-d', strtotime('next Sunday', strtotime($date)));
            return [
                'start' => $start,
                'end' => $end,
                'format' => 'Y-m-d'
            ];
            
        case 'month':
            $year = date('Y', strtotime($date));
            $month = date('m', strtotime($date));
            $days = date('t', strtotime("$year-$month-01"));
            $start = "$year-$month-01";
            $end = "$year-$month-$days";
            return [
                'start' => $start,
                'end' => $end,
                'format' => 'Y-m-d'
            ];
            
        case 'months12':
            $start = date('Y-m-01', strtotime('-11 months', strtotime($date)));
            $end = date('Y-m-t', strtotime('now'));
            return [
                'start' => $start,
                'end' => $end,
                'format' => 'Y-m-d'
            ];
            
        default:
            return [
                'start' => '',
                'end' => '',
                'format' => 'Y-m-d'
            ];
    }
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
    
    $range = getPeriodRange($period, $date);
    $start = $range['start'];
    $end = $range['end'];
    
    switch ($period) {
        case 'day':
            return array_filter($validData, function($record) use ($start) {
                return $record['date'] === $start;
            });
            
        case 'week':
            return array_filter($validData, function($record) use ($start, $end) {
                return $record['date'] >= $start && $record['date'] <= $end;
            });
            
        case 'month':
            return array_filter($validData, function($record) use ($start, $end) {
                return $record['date'] >= $start && $record['date'] <= $end;
            });
            
        case 'months12':
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

// 获取最近12个月的统计
$months12Stats = [];
$months12Range = getPeriodRange('months12', $currentDate);

// 生成最近12个月的列表
$monthsList = [];
$currentMonth = new DateTime($months12Range['start']);
$endMonth = new DateTime($months12Range['end']);
while ($currentMonth <= $endMonth) {
    $monthKey = $currentMonth->format('Y-m');
    $monthsList[] = $monthKey;
    
    // 计算该月的统计数据
    $monthStart = $currentMonth->format('Y-m-01');
    $monthEnd = $currentMonth->format('Y-m-t');
    
    $monthData = array_filter($data, function($record) use ($monthStart, $monthEnd) {
        return $record['date'] >= $monthStart && $record['date'] <= $monthEnd;
    });
    
    $monthSummary = [
        'reading' => 0,
        'sharing' => 0,
        'exercise' => 0,
        'total' => 0,
        'peopleCount' => count(array_unique(array_column($monthData, 'name'))),
        'details' => calculateStats($monthData)
    ];
    
    foreach ($monthData as $record) {
        $monthSummary['reading'] += $record['reading'] ?? 0;
        $monthSummary['sharing'] += $record['sharing'] ?? 0;
        
        // 计算运动积分
        if (!empty($record['exercise'])) {
            $week = date('Y-W', strtotime($record['date']));
            $monthSummary['exercise'] += 1; // 先累计次数
        }
    }
    
    // 计算实际运动积分（每周至少3次得3分）
    $weeks = [];
    foreach ($monthData as $record) {
        if (!empty($record['exercise'])) {
            $week = date('Y-W', strtotime($record['date']));
            $weeks[$week] = ($weeks[$week] ?? 0) + 1;
        }
    }
    
    $monthSummary['exercise'] = 0;
    foreach ($weeks as $count) {
        if ($count >= 3) $monthSummary['exercise'] += 3;
    }
    
    $monthSummary['total'] = $monthSummary['reading'] + $monthSummary['sharing'] + $monthSummary['exercise'];
    $months12Stats[$monthKey] = $monthSummary;
    
    $currentMonth->modify('+1 month');
}

// 获取时间段范围
$dayRange = getPeriodRange('day', $currentDate);
$weekRange = getPeriodRange('week', $currentDate);
$monthRange = getPeriodRange('month', $currentDate);
$months12Range = getPeriodRange('months12', $currentDate);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>运动读书打卡学分统计</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1 {
            text-align: center;
            color: #333;
        }
        
        .card {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .login-form {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .period-selector {
            margin-bottom: 20px;
        }
        
        .period-selector button {
            padding: 8px 15px;
            margin-right: 5px;
            border: 1px solid #ddd;
            background-color: white;
            cursor: pointer;
        }
        
        .period-selector button.active {
            background-color: #4CAF50;
            color: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table, th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        
        th {
            background-color: #f2f2f2;
            text-align: left;
        }
        
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .btn {
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            color: white;
            display: inline-block;
            margin-right: 5px;
        }
        
        .btn-edit {
            background-color: #2196F3;
        }
        
        .btn-delete {
            background-color: #f44336;
        }
        
        .btn-add {
            background-color: #4CAF50;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
        }
        
        input, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .error {
            color: red;
        }
        
        .logout {
            text-align: right;
            margin-bottom: 20px;
        }
        
        .stats-container {
            display: none;
        }
        
        .stats-container.active {
            display: block;
        }
        
        .month-stats {
            margin-top: 20px;
        }
        
        .month-buttons {
            margin-bottom: 15px;
        }
        
        .month-buttons button {
            padding: 6px 10px;
            margin: 2px;
            border: 1px solid #ddd;
            background-color: white;
            cursor: pointer;
        }
        
        .month-buttons button.active {
            background-color: #4CAF50;
            color: white;
        }
    </style>
</head>
<body>
    <h1>运动读书打卡学分统计</h1>
    
    <?php if (!isAdmin()): ?>
        <div class="card login-form">
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
                <button type="submit" name="login" class="btn btn-add">登录</button>
            </form>
        </div>
    <?php else: ?>
        <div class="logout">
            <a href="?logout=1">退出登录</a>
        </div>
        
        <!-- 数据录入/编辑表单 -->
        <div class="card">
            <h2><?php echo $editData ? '编辑打卡记录' : '添加打卡记录'; ?></h2>
            <form method="post" id="recordForm">
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
                    <div>
                        <input type="date" id="date" name="date" value="<?php echo $editData ? $editData['date'] : date('Y-m-d'); ?>" required>
                        <span id="weekday"><?php echo $editData ? getWeekday($editData['date']) : getWeekday(date('Y-m-d')); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label>读书点评:</label>
                    <input type="checkbox" id="reading" name="reading" <?php echo ($editData && $editData['reading'] == 1) ? 'checked' : ''; ?>> 参与 (1分)
                </div>
                <div class="form-group">
                    <label>优秀分享:</label>
                    <input type="checkbox" id="sharing" name="sharing" <?php echo ($editData && $editData['sharing'] > 0) ? 'checked' : ''; ?>> 被评为优秀分享 (3分)
                    <select id="sharing_score" name="sharing_score" <?php echo ($editData && $editData['sharing'] > 0) ? '' : 'disabled'; ?>>
                        <option value="0">请选择分数</option>
                        <option value="90" <?php echo ($editData && $editData['sharingScore'] == 90) ? 'selected' : ''; ?>>90</option>
                        <option value="95" <?php echo ($editData && $editData['sharingScore'] == 95) ? 'selected' : ''; ?>>95</option>
                        <option value="100" <?php echo ($editData && $editData['sharingScore'] == 100) ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>健康运动:</label>
                    <input type="checkbox" id="exercise" name="exercise" <?php echo ($editData && $editData['exercise'] == 1) ? 'checked' : ''; ?>> 参与 (每周至少3次自动得3分，不满3次不计分)
                </div>
                <button type="submit" name="<?php echo $editData ? 'edit' : 'add'; ?>" class="btn btn-add">
                    <?php echo $editData ? '保存修改' : '添加记录'; ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
    
    <!-- 最近记录 -->
    <div class="card">
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
                    <td><?php echo htmlspecialchars($record['team']); ?></td>
                    <td><?php echo htmlspecialchars($record['name']); ?></td>
                    <td><?php echo $record['reading'] ? '1分' : '0分'; ?></td>
                    <td><?php echo $record['sharing'] ? '3分' : '0分'; ?></td>
                    <td><?php echo $record['sharingScore'] ?: '-'; ?></td>
                    <td><?php echo $record['exercise'] ? '记录' : '未记录'; ?></td>
                    <?php if (isAdmin()): ?>
                        <td>
                            <a href="?edit=<?php echo $record['id']; ?>" class="btn btn-edit">编辑</a>
                            <a href="?delete=<?php echo $record['id']; ?>" class="btn btn-delete" onclick="return confirm('确定要删除这条记录吗？')">删除</a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <!-- 统计数据 -->
    <div class="card">
        <h2>统计数据</h2>
        
        <div class="period-selector">
            <button onclick="showStats('day')">当天</button>
            <button onclick="showStats('week')">本周</button>
            <button onclick="showStats('month')">本月</button>
            <button onclick="showStats('months12')" class="active">最近12个月</button>
        </div>
        
        <!-- 个人统计 -->
        <div id="day-stats" class="stats-container">
            <h3>当天统计 (<?php echo date('Y年m月d日', strtotime($dayRange['start'])); ?>)</h3>
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
                        <td><?php echo htmlspecialchars($name); ?></td>
                        <td><?php echo htmlspecialchars($stats['team']); ?></td>
                        <td><?php echo $stats['reading']; ?></td>
                        <td><?php echo $stats['sharing']; ?></td>
                        <td><?php echo $stats['exercise']; ?></td>
                        <td><?php echo $stats['total']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div id="week-stats" class="stats-container" style="display: none;">
            <h3>本周统计 (<?php echo date('Y年m月d日', strtotime($weekRange['start'])); ?> 至 <?php echo date('Y年m月d日', strtotime($weekRange['end'])); ?>)</h3>
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
                        <td><?php echo htmlspecialchars($name); ?></td>
                        <td><?php echo htmlspecialchars($stats['team']); ?></td>
                        <td><?php echo $stats['reading']; ?></td>
                        <td><?php echo $stats['sharing']; ?></td>
                        <td><?php echo $stats['exercise']; ?></td>
                        <td><?php echo $stats['total']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div id="month-stats" class="stats-container" style="display: none;">
            <h3>本月统计 (<?php echo date('Y年m月d日', strtotime($monthRange['start'])); ?> 至 <?php echo date('Y年m月d日', strtotime($monthRange['end'])); ?>)</h3>
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
                        <td><?php echo htmlspecialchars($name); ?></td>
                        <td><?php echo htmlspecialchars($stats['team']); ?></td>
                        <td><?php echo $stats['reading']; ?></td>
                        <td><?php echo $stats['sharing']; ?></td>
                        <td><?php echo $stats['exercise']; ?></td>
                        <td><?php echo $stats['total']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div id="months12-stats" class="stats-container active">
            <h3>最近12个月统计</h3>
            
            <div class="month-buttons">
                <?php foreach (array_reverse($monthsList) as $monthKey): ?>
                    <button onclick="showMonthStats('<?php echo $monthKey; ?>')" <?php echo ($monthKey === end($monthsList)) ? 'class="active"' : ''; ?>>
                        <?php echo date('Y年m月', strtotime($monthKey . '-01')); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <?php foreach ($months12Stats as $monthKey => $monthStats): ?>
                <div id="month-stats-<?php echo $monthKey; ?>" class="month-stats" <?php echo ($monthKey !== end($monthsList)) ? 'style="display: none;"' : ''; ?>>
                    <h4><?php echo date('Y年m月', strtotime($monthKey . '-01')); ?> 统计</h4>
                    <div class="month-summary">
                        <p>参与人数: <?php echo $monthStats['peopleCount']; ?></p>
                        <p>读书点评总分: <?php echo $monthStats['reading']; ?></p>
                        <p>优秀分享总分: <?php echo $monthStats['sharing']; ?></p>
                        <p>健康运动总分: <?php echo $monthStats['exercise']; ?></p>
                        <p>总积分: <?php echo $monthStats['total']; ?></p>
                    </div>
                    
                    <table>
                        <tr>
                            <th>姓名</th>
                            <th>团队</th>
                            <th>读书点评</th>
                            <th>优秀分享</th>
                            <th>健康运动</th>
                            <th>总积分</th>
                        </tr>
                        <?php foreach ($monthStats['details'] as $name => $stats): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($name); ?></td>
                                <td><?php echo htmlspecialchars($stats['team']); ?></td>
                                <td><?php echo $stats['reading']; ?></td>
                                <td><?php echo $stats['sharing']; ?></td>
                                <td><?php echo $stats['exercise']; ?></td>
                                <td><?php echo $stats['total']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endforeach; ?>
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
                    period === 'week' ? '本周' : 
                    period === 'month' ? '本月' : '最近12个月')
            );
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
        }
        
        // 显示指定月份的统计数据
        function showMonthStats(monthKey) {
            document.querySelectorAll('.month-stats').forEach(el => {
                el.style.display = 'none';
            });
            document.querySelectorAll('.month-buttons button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            const statsEl = document.getElementById(`month-stats-${monthKey}`);
            if (statsEl) {
                statsEl.style.display = 'block';
            }
            
            const activeBtn = Array.from(document.querySelectorAll('.month-buttons button')).find(btn => 
                btn.textContent.includes(dateFormat(monthKey + '-01', 'Y年m月'))
            );
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
        }
        
        // 辅助函数：格式化日期
        function dateFormat(dateStr, format) {
            const date = new Date(dateStr);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            
            return format.replace('Y', year).replace('m', month).replace('d', day);
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
        
        // 优秀分享复选框变更时控制分数选择
        document.getElementById('sharing').addEventListener('change', function() {
            const scoreSelect = document.getElementById('sharing_score');
            if (this.checked) {
                scoreSelect.disabled = false;
            } else {
                scoreSelect.disabled = true;
                scoreSelect.value = 0;
            }
        });
        
        // 表单提交前验证
        document.getElementById('recordForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value;
            const newName = document.getElementById('new_name').value;
            
            if (!name && !newName) {
                alert('请选择或输入姓名');
                e.preventDefault();
                return false;
            }
            
            const sharing = document.getElementById('sharing').checked;
            const sharingScore = document.getElementById('sharing_score').value;
            
            if (sharing && sharingScore <= 0) {
                alert('被评为优秀分享时必须选择分数');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>
