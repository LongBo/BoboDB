
=============


使用PDO连接数据库

封装示例：
<pre>
$selector = [
    'table' => [ 'tablename1 t1'],
    'cols' => [ 'id', 'username'],
    'join' => ['left', 'table2 t2'],
    'on' => 't2.uid = t1.id',
    'where' => 'id = :id', 
    'order' => 'id desc', 
    'group' => 'user', 
    'limit' => '0, 20'
    ];
$result = $db->getAll($selector, [':id' => $id]);

$inserter = [
    'table' => 'table',
    'cols' => [ 'id', 'username'],
    'values' => [['id1', 'name1'],['id2', 'name2']]
    ];
$db->insert($insertor, [':id' => $id], $ignore = true);

$replacer = [
    'table' => 'table',
    'cols' => [ 'id', 'username'],
    'values' => [['id1', 'name1'],['id2', 'name2']]
    ];
$db->insert($replacer, [':id' => $id]);

$updater = [
    'table' => 'table',
    'set' => [ 'id'=>':id', 'username' => ':username'],
    'where' => 'id=:id',
    ];
$db->update($updater, [':id' => $id]);

$deleter = [
    'table' => 'table',
    'where' => 'id=:id',
    ];
$db->delete($deleter, [':id' => $id]);
</pre>

