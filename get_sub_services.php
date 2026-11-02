<?php
include 'config/database.php';

header('Content-Type: application/json');

$service_id = isset($_GET['service_id'])
    ? intval($_GET['service_id'])
    : 0;

if ($service_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT id, sub_service_name
     FROM service_subcategories
     WHERE service_id = ? AND status = 'active'
     ORDER BY sub_service_name"
);

$stmt->bind_param("i", $service_id);
$stmt->execute();

$result = $stmt->get_result();

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);

$stmt->close();
?>