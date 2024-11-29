<?php
class DeviceController{
  public function insert_data(){
    global $conn;
    date_default_timezone_set('Asia/Manila');
    $response = array();
  
    $data = json_decode(file_get_contents("php://input"), true);
    $temp = isset($data['temp']) ? htmlspecialchars($data['temp']) : '';
    $do = isset($data['do']) ? htmlspecialchars($data['do']) : '';
    $ph = isset($data['ph']) ? htmlspecialchars($data['ph']) : '';
    $ammonia = isset($data['ammonia']) ? htmlspecialchars($data['ammonia']) : '';
    $created_at = date('Y-m-d H:i:s');
  
    // Validate inputs
    if (empty($temp)) {
      $response['status'] = 'error';
      $response['message'] = 'Temperature cannot be empty';
      echo json_encode($response);
      return;
    }
  
    if (empty($do)) {
      $response['status'] = 'error';
      $response['message'] = 'Dissolved Oxygen cannot be empty';
      echo json_encode($response);
      return;
    }
  
    if (empty($ph)) {
      $response['status'] = 'error';
      $response['message'] = 'PH cannot be empty';
      echo json_encode($response);
      return;
    }
  
    // Insert data into ras_data
    $stmt = $conn->prepare('INSERT INTO ras_data (temp, do, ph, ammonia, created_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $temp, $do, $ph, $ammonia, $created_at);
    
    if ($stmt->execute()) {
      $response['status'] = 'success';
      $response['message'] = 'Data inserted successfully';
  
      // Validate ranges and insert alerts into ras_history
      $this->validate_and_log($conn, 'pH Level', $ph, 6.5, 8.6, [
        'low' => 'The water is too acidic. Consider adding a pH increaser or buffering agent to raise the pH. Ensure gradual adjustments and recheck pH after 24 hours.',
        'high' => 'The water is too alkaline. Consider adding a pH reducer or acidic buffer to bring the pH back to a balanced level. Monitor closely and make adjustments gradually to avoid sudden changes that could stress the aquatic life.'
      ], $created_at);
  
      $this->validate_and_log($conn, 'Dissolved Oxygen', $do, 6, 9, [
        'low' => 'Dissolved oxygen is low, which can lead to stress in fish. Increase aeration by adding air stones, adjusting flow rate, or installing additional water pumps to circulate and oxygenate the water.',
        'high' => 'Dissolved oxygen is very high. If you\'re noticing bubbles or gas bubble disease in fish, reduce aeration slightly and monitor. Typically, natural fluctuations will lower the DO.'
      ], $created_at);
  
      $this->validate_and_log($conn, 'Temperature', $temp, 22, 28, [
        'low' => 'Temperature is below the safe range. Adjust the heater setting or place insulation around the tank to stabilize the temperature. Avoid sudden increases that could stress fish or other organisms.',
        'high' => 'Temperature is above the safe range. Turn down heaters, increase cooling by adding ice packs carefully or using a fan, and check surrounding equipment. Avoid drastic temperature changes that could shock aquatic life.'
      ], $created_at);
  
      $this->validate_and_log($conn, 'Ammonia', $ammonia, 0.00, 0.08, [
        'high' => 'Ammonia levels are dangerously high and could harm aquatic life. Perform a partial water change (20–30%) to dilute ammonia levels, and check your filtration system. Consider adding beneficial bacteria to help convert ammonia to less toxic forms.'
      ], $created_at);
  
      echo json_encode($response);
    } else {
      $response['status'] = 'error';
      $response['message'] = 'Error inserting data: ' . $conn->error;
      echo json_encode($response);
    }
  }
  
  private function validate_and_log($conn, $title, $value, $min, $max, $suggestions, $created_at) {
    if ($value < $min) {
      $stmt = $conn->prepare('INSERT INTO ras_history (title, description, value, created_at) VALUES (?, ?, ?, ?)');
      $stmt->bind_param('ssss', $title, $suggestions['low'], $value, $created_at);
      $stmt->execute();
    } elseif ($value > $max) {
      $stmt = $conn->prepare('INSERT INTO ras_history (title, description, value, created_at) VALUES (?, ?, ?, ?)');
      $stmt->bind_param('ssss', $title, $suggestions['high'], $value, $created_at);
      $stmt->execute();
    }
  }  

  public function get_data_by_parameter(){
    global $conn;
    date_default_timezone_set('Asia/Manila');
    $response = array();
  
    // Retrieve parameters from $_GET
    $param = isset($_GET['param']) ? $_GET['param'] : null;
    $range = isset($_GET['range']) ? $_GET['range'] : 'all';
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
  
    // Validate that the parameter is one of the allowed columns
    $allowedParams = ['temp', 'do', 'ph', 'ammonia'];
    if (!in_array($param, $allowedParams)) {
      $response['status'] = 'error';
      $response['message'] = 'Invalid parameter selected.';
      echo json_encode($response);
      return;
    }
  
    // Initialize conditions array
    $conditions = array();
  
    // Add condition for the specific parameter if provided
    if (!empty($_GET[$param])) {
      $conditions[] = "$param = ?";
      $types = "s";
      $params = [$_GET[$param]];
    } else {
      $types = "";
      $params = [];
    }
  
    // Add start_date and end_date condition if provided and valid
    if (!empty($startDate) && !empty($endDate)) {
      // Validate date format (YYYY-MM-DD)
      $startDate = DateTime::createFromFormat('Y-m-d', $startDate);
      $endDate = DateTime::createFromFormat('Y-m-d', $endDate);
      if (!$startDate || !$endDate) {
        $response['status'] = 'error';
        $response['message'] = 'Invalid date format. Please use YYYY-MM-DD.';
        echo json_encode($response);
        return;
      }
  
      $conditions[] = "DATE(created_at) BETWEEN ? AND ?";
      $types .= "ss";  // Add types for two date parameters
      $params[] = $startDate->format('Y-m-d');
      $params[] = $endDate->format('Y-m-d');
    }
  
    // Add date range condition based on the range parameter
    switch ($range) {
      case 'yearly':
        $conditions[] = "YEAR(created_at) = YEAR(CURDATE())";
        break;
      case 'monthly':
        $conditions[] = "YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
        break;
      case 'weekly':
        $conditions[] = "YEAR(created_at) = YEAR(CURDATE()) AND WEEK(created_at) = WEEK(CURDATE())";
        break;
      case 'daily':
        $conditions[] = "DATE(created_at) = CURDATE()";
        break;
      case 'all':
      default:
        // No additional conditions for 'all'
        break;
    }
  
    // Build the query with dynamic conditions
    $sql = "SELECT * FROM ras_data";
    if (count($conditions) > 0) {
      $sql .= " WHERE " . implode(" AND ", $conditions);
    }
  
    $stmt = $conn->prepare($sql);
  
    // Bind parameters if there are any
    if (!empty($params)) {
      $stmt->bind_param($types, ...$params);
    }
  
    // Execute and fetch data
    if ($stmt->execute()) {
      $result = $stmt->get_result();
      $data_array = $result->fetch_all(MYSQLI_ASSOC);
      $response['status'] = 'success';
      $response['data'] = $data_array;
    } else {
      $response['status'] = 'error';
      $response['message'] = 'Error retrieving data: ' . $conn->error;
    }
  
    echo json_encode($response);
  }   
  
  public function get_latest_values(){
    global $conn;
    date_default_timezone_set('Asia/Manila');
    $response = array();
  
    // Retrieve the latest value for each parameter
    $allowedParams = ['temp', 'do', 'ph', 'ammonia'];
    $latestValues = array();
  
    // Loop through each parameter to get the latest value
    foreach ($allowedParams as $param) {
      $sql = "SELECT $param, created_at FROM ras_data WHERE $param IS NOT NULL ORDER BY created_at DESC LIMIT 1";
      $stmt = $conn->prepare($sql);
  
      if ($stmt->execute()) {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
  
        if ($row) {
          $latestValues[$param] = [
            'value' => $row[$param],
            'created_at' => $row['created_at']
          ];
        } else {
          $latestValues[$param] = [
            'value' => null,
            'created_at' => null
          ];
        }
      } else {
        $response['status'] = 'error';
        $response['message'] = 'Error retrieving latest value for ' . $param;
        echo json_encode($response);
        return;
      }
    }
  
    // Send the response
    $response['status'] = 'success';
    $response['data'] = $latestValues;
    echo json_encode($response);
  }  

  public function history() {
    global $conn;
    date_default_timezone_set('Asia/Manila');
    $response = array();
  
    // Define optional filters
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    $range = isset($_GET['range']) ? $_GET['range'] : 'all';
    $page = isset($_GET['page']) ? $_GET['page'] : 1; // Get the page number
    $limit = 10; // Number of records per page
    $offset = ($page - 1) * $limit; // Calculate the offset
  
    // Initialize query and conditions
    $sql = "SELECT * FROM ras_history";
    $conditions = array();
    $types = "";
    $params = array();
  
    // Add date range condition
    if (!empty($startDate) && !empty($endDate)) {
      // Validate date format (YYYY-MM-DD)
      $startDate = DateTime::createFromFormat('Y-m-d', $startDate);
      $endDate = DateTime::createFromFormat('Y-m-d', $endDate);
  
      if (!$startDate || !$endDate) {
        $response['status'] = 'error';
        $response['message'] = 'Invalid date format. Please use YYYY-MM-DD.';
        echo json_encode($response);
        return;
      }
  
      $conditions[] = "DATE(created_at) BETWEEN ? AND ?";
      $types .= "ss";
      $params[] = $startDate->format('Y-m-d');
      $params[] = $endDate->format('Y-m-d');
    }
  
    // Add predefined range conditions
    switch ($range) {
      case 'yearly':
        $conditions[] = "YEAR(created_at) = YEAR(CURDATE())";
        break;
      case 'monthly':
        $conditions[] = "YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
        break;
      case 'weekly':
        $conditions[] = "YEAR(created_at) = YEAR(CURDATE()) AND WEEK(created_at) = WEEK(CURDATE())";
        break;
      case 'daily':
        $conditions[] = "DATE(created_at) = CURDATE()";
        break;
      case 'all':
      default:
        // No conditions for 'all'
        break;
    }
  
    // Add conditions to SQL
    if (count($conditions) > 0) {
      $sql .= " WHERE " . implode(" AND ", $conditions);
    }
  
    // Add pagination to the query
    $sql .= " LIMIT ? OFFSET ?";
    $types .= "ii";
    $params[] = $limit;
    $params[] = $offset;
  
    // Prepare statement
    $stmt = $conn->prepare($sql);
  
    if (!empty($params)) {
      $stmt->bind_param($types, ...$params);
    }
  
    // Execute query and fetch data
    if ($stmt->execute()) {
      $result = $stmt->get_result();
      $data_array = $result->fetch_all(MYSQLI_ASSOC);
  
      // Get the total number of rows for pagination
      $countSql = "SELECT COUNT(*) as total FROM ras_history";
      if (count($conditions) > 0) {
        $countSql .= " WHERE " . implode(" AND ", $conditions);
      }
  
      $countStmt = $conn->prepare($countSql);
      if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
      }
  
      $countStmt->execute();
      $countResult = $countStmt->get_result()->fetch_assoc();
      $totalRecords = $countResult['total'];
  
      // Calculate total pages
      $totalPages = ceil($totalRecords / $limit);
  
      $response['status'] = 'success';
      $response['data'] = $data_array;
      $response['pagination'] = [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords
      ];
    } else {
      $response['status'] = 'error';
      $response['message'] = 'Error retrieving history: ' . $conn->error;
    }
  
    // Return response
    echo json_encode($response);
  }  
}
?>