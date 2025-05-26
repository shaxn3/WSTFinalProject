<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$xmlFile = 'members.xml';

$action = $_GET['action'] ?? '';

function readMembers($xmlFile) {
    if (!file_exists($xmlFile)) {
        $xml = new SimpleXMLElement('<?xml version="1.0"?><members></members>');
        $dom = new DOMDocument('1.0');
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $dom->save($xmlFile);
        return [];
    }

    try {
        $xml = simplexml_load_file($xmlFile);
        if ($xml === false) {
            throw new Exception('Failed to parse XML file');
        }

        $members = [];
        if (isset($xml->member)) {
            foreach ($xml->member as $m) {
                $members[] = [
                    'id' => (string)$m->id,
                    'name' => (string)$m->name,
                    'email' => (string)$m->email,
                    'course' => (string)$m->course,
                    'picture' => (string)($m->picture ?? '')
                ];
            }
        }

        return $members;
    } catch (Exception $e) {
        error_log('Error reading XML: ' . $e->getMessage());
        return [];
    }
}

function saveMembers($xmlFile, $members) {
    try {
        $xml = new SimpleXMLElement('<?xml version="1.0"?><members></members>');

        foreach ($members as $m) {
            $member = $xml->addChild('member');
            $member->addChild('id', htmlspecialchars($m['id']));
            $member->addChild('name', htmlspecialchars($m['name']));
            $member->addChild('email', htmlspecialchars($m['email']));
            $member->addChild('course', htmlspecialchars($m['course']));
            $member->addChild('picture', htmlspecialchars($m['picture'] ?? ''));
        }

        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        if ($dom->save($xmlFile) === false) {
            throw new Exception('Failed to save XML file');
        }

        chmod($xmlFile, 0666);
        
        return true;
    } catch (Exception $e) {
        error_log('Error saving XML: ' . $e->getMessage());
        return false;
    }
}

function validateMember($member) {
    $errors = [];

    if (empty($member['name']) || strlen(trim($member['name'])) < 2) {
        $errors[] = 'Name must be at least 2 characters';
    }

    if (empty($member['email']) || !filter_var($member['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }

    if (empty($member['course']) || strlen(trim($member['course'])) < 2) {
        $errors[] = 'Course is required';
    }

    return $errors;
}

function generateNextId($existingMembers) {
    $year = date('Y');
    $maxId = 0;

    foreach ($existingMembers as $member) {
        if (preg_match('/^' . $year . '-(\d{3})$/', $member['id'], $matches)) {
            $maxId = max($maxId, intval($matches[1]));
        }
    }

    return $year . '-' . str_pad($maxId + 1, 3, '0', STR_PAD_LEFT);
}

function memberIdExists($members, $id) {
    foreach ($members as $member) {
        if ($member['id'] === $id) {
            return true;
        }
    }
    return false;
}

function isValidBase64Image($base64String) {
    if (!preg_match('/^data:image\/(jpeg|jpg|png|gif);base64,/', $base64String)) {
        return false;
    }
    
    $base64Data = substr($base64String, strpos($base64String, ',') + 1);
    
    if (!base64_decode($base64Data, true)) {
        return false;
    }
    
    $decodedSize = strlen(base64_decode($base64Data));
    if ($decodedSize > 2 * 1024 * 1024) {
        return false;
    }
    
    return true;
}

try {
    switch ($action) {
        case 'read':
            $members = readMembers($xmlFile);
            echo json_encode($members);
            break;

        case 'save':
            $input = file_get_contents('php://input');
            $members = json_decode($input, true);

            if ($members === null) {
                throw new Exception('Invalid JSON data');
            }

            $allErrors = [];
            foreach ($members as $index => $member) {
                $errors = validateMember($member);
                if (!empty($errors)) {
                    $allErrors["member_$index"] = $errors;
                }
            }

            if (!empty($allErrors)) {
                http_response_code(400);
                echo json_encode(['error' => 'Validation failed', 'details' => $allErrors]);
                break;
            }

            if (saveMembers($xmlFile, $members)) {
                echo json_encode(['success' => true, 'message' => 'Members saved successfully']);
            } else {
                throw new Exception('Failed to save members to file');
            }
            break;

        case 'add':
            $input = file_get_contents('php://input');
            $newMember = json_decode($input, true);

            if ($newMember === null) {
                throw new Exception('Invalid JSON data');
            }

            $newMember['name'] = trim($newMember['name'] ?? '');
            $newMember['email'] = trim($newMember['email'] ?? '');
            $newMember['course'] = trim($newMember['course'] ?? '');
            $newMember['picture'] = $newMember['picture'] ?? '';

            if (!empty($newMember['picture'])) {
                if (!isValidBase64Image($newMember['picture'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid image format']);
                    break;
                }
            }

            $errors = validateMember($newMember);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
                break;
            }

            $members = readMembers($xmlFile);

            if (empty($newMember['id'])) {
                $newMember['id'] = generateNextId($members);
            }

            if (memberIdExists($members, $newMember['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Member ID already exists']);
                break;
            }

            foreach ($members as $member) {
                if (strtolower($member['email']) === strtolower($newMember['email'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Email already exists']);
                    break 2;
                }
            }

            $members[] = $newMember;

            if (saveMembers($xmlFile, $members)) {
                echo json_encode([
                    'success' => true, 
                    'member' => $newMember, 
                    'message' => 'Member added successfully'
                ]);
            } else {
                throw new Exception('Failed to save member');
            }
            break;

        case 'update':
            $memberId = $_GET['id'] ?? '';
            if (empty($memberId)) {
                throw new Exception('Member ID is required');
            }

            $input = file_get_contents('php://input');
            $updatedMember = json_decode($input, true);

            if ($updatedMember === null) {
                throw new Exception('Invalid JSON data');
            }

            $updatedMember['name'] = trim($updatedMember['name'] ?? '');
            $updatedMember['email'] = trim($updatedMember['email'] ?? '');
            $updatedMember['course'] = trim($updatedMember['course'] ?? '');
            $updatedMember['picture'] = $updatedMember['picture'] ?? '';

            if (!empty($updatedMember['picture'])) {
                if (!isValidBase64Image($updatedMember['picture'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid image format']);
                    break;
                }
            }

            $updatedMember['id'] = $memberId;

            $errors = validateMember($updatedMember);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
                break;
            }

            $members = readMembers($xmlFile);

            $found = false;
            $memberIndex = -1;
            for ($i = 0; $i < count($members); $i++) {
                if ($members[$i]['id'] === $memberId) {
                    $memberIndex = $i;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                http_response_code(404);
                echo json_encode(['error' => 'Member not found']);
                break;
            }

            foreach ($members as $index => $member) {
                if ($index !== $memberIndex && 
                    strtolower($member['email']) === strtolower($updatedMember['email'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Email already exists']);
                    break 2;
                }
            }

            $members[$memberIndex] = $updatedMember;

            if (saveMembers($xmlFile, $members)) {
                echo json_encode([
                    'success' => true, 
                    'member' => $updatedMember, 
                    'message' => 'Member updated successfully'
                ]);
            } else {
                throw new Exception('Failed to save member');
            }
            break;

        case 'delete':
            $memberId = $_GET['id'] ?? '';
            if (empty($memberId)) {
                throw new Exception('Member ID is required');
            }

            $members = readMembers($xmlFile);

            $originalCount = count($members);
            $members = array_filter($members, function($member) use ($memberId) {
                return $member['id'] !== $memberId;
            });

            if (count($members) === $originalCount) {
                http_response_code(404);
                echo json_encode(['error' => 'Member not found']);
                break;
            }

            $members = array_values($members);

            if (saveMembers($xmlFile, $members)) {
                echo json_encode(['success' => true, 'message' => 'Member deleted successfully']);
            } else {
                throw new Exception('Failed to save changes');
            }
            break;

        case 'stats':
            $members = readMembers($xmlFile);
            $stats = [
                'total' => count($members),
                'courses' => []
            ];

            foreach ($members as $member) {
                $course = strtoupper($member['course']);
                if (!isset($stats['courses'][$course])) {
                    $stats['courses'][$course] = 0;
                }
                $stats['courses'][$course]++;
            }

            echo json_encode($stats);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action. Supported actions: read, save, add, update, delete, stats']);
            break;
    }

} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

exit;
?>
