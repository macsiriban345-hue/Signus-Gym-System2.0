<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'signus_gym';
$username = 'root';
$password = '';

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $pdo = null;
}

// Handle form submissions for main page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'register':
                handleRegistration();
                break;
            case 'login':
                handleLogin();
                break;
            case 'forgot_password':
                handleForgotPassword();
                break;
            case 'create_announcement':
                handleCreateAnnouncement();
                break;
            case 'add_comment':
                handleAddComment();
                break;
            case 'confirm_payment':
                handleConfirmPayment();
                break;
            case 'check_in_user':
                handleCheckIn();
                break;
            case 'check_out_user':
                handleCheckOut();
                break;
            case 'setup_account':
                handleAccountSetup();
                break;
            case 'create_workout_plan':
                handleCreateWorkoutPlan();
                break;
            case 'update_client_progress':
                handleUpdateClientProgress();
                break;
            case 'send_message':
                handleSendMessage();
                break;
            case 'update_availability':
                handleUpdateAvailability();
                break;
            case 'activate_user':
                handleActivateUser();
                break;
            case 'reject_user':
                handleRejectUser();
                break;
            case 'create_user_credentials':
                handleCreateUserCredentials();
                break;
            // NEW MESSAGING ACTIONS
            case 'send_new_message':
                handleSendNewMessage();
                break;
            case 'mark_message_read':
                handleMarkMessageRead();
                break;
            case 'book_coaching':
                handleBookCoaching();
                break;
            case 'update_coaching_status':
                handleUpdateCoachingStatus();
                break;
        }
    }
}

// =============================================================================
// NEW MESSAGING FUNCTIONS
// =============================================================================

function handleSendNewMessage() {
    global $pdo;
    
    if (!isset($_SESSION['user'])) {
        $_SESSION['error'] = "Please login to send messages!";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    
    $sender_id = $_SESSION['user']['user_id'];
    $receiver_id = $_POST['receiver_id'];
    $subject = htmlspecialchars($_POST['subject'] ?? '');
    $message = htmlspecialchars($_POST['message']);
    
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$sender_id, $receiver_id, $subject, $message]);
            
            $_SESSION['success'] = "Message sent successfully!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Failed to send message: " . $e->getMessage();
        }
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function handleMarkMessageRead() {
    global $pdo;
    
    if (!isset($_SESSION['user'])) {
        exit;
    }
    
    $message_id = $_POST['message_id'];
    $user_id = $_SESSION['user']['user_id'];
    
    if ($pdo) {
        try {
            // Check if message status already exists
            $stmt = $pdo->prepare("SELECT id FROM message_status WHERE message_id = ? AND user_id = ?");
            $stmt->execute([$message_id, $user_id]);
            
            if ($stmt->fetch()) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE message_status SET is_read = 1, read_at = NOW() WHERE message_id = ? AND user_id = ?");
                $stmt->execute([$message_id, $user_id]);
            } else {
                // Insert new
                $stmt = $pdo->prepare("INSERT INTO message_status (message_id, user_id, is_read, read_at) VALUES (?, ?, 1, NOW())");
                $stmt->execute([$message_id, $user_id]);
            }
            
            echo "success";
        } catch(PDOException $e) {
            echo "error";
        }
    }
    exit;
}

function handleBookCoaching() {
    global $pdo;
    
    if (!isset($_SESSION['user'])) {
        $_SESSION['error'] = "Please login to book coaching!";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    
    $user_id = $_SESSION['user']['user_id'];
    $service_type = $_POST['service_type'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $trainer_id = $_POST['trainer_id'];
    $notes = htmlspecialchars($_POST['notes'] ?? '');
    
    $appointment_datetime = $appointment_date . ' ' . $appointment_time . ':00';
    
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO coaching_services (user_id, service_type, appointment_date, assigned_trainer_id, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $service_type, $appointment_datetime, $trainer_id, $notes]);
            
            $_SESSION['success'] = "Coaching session booked successfully!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Failed to book coaching session: " . $e->getMessage();
        }
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function handleUpdateCoachingStatus() {
    global $pdo;
    
    if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['role'] !== 'trainer')) {
        $_SESSION['error'] = "Access denied!";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    
    $session_id = $_POST['session_id'];
    $status = $_POST['status'];
    
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("UPDATE coaching_services SET status = ? WHERE id = ?");
            $stmt->execute([$status, $session_id]);
            
            $_SESSION['success'] = "Session status updated successfully!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Failed to update session: " . $e->getMessage();
        }
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// =============================================================================
// EXISTING FUNCTIONS (UNCHANGED)
// =============================================================================

// NEW FUNCTION: Handle creating username and password after payment confirmation
function handleCreateUserCredentials() {
    global $pdo;
    
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        $_SESSION['error'] = "Access denied!";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    
    $user_id = $_POST['user_id'];
    $username = htmlspecialchars($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match!";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    
    // Validate password strength
    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long!";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    
    if ($pdo) {
        try {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = "Username already exists! Please choose a different one.";
                header("Location: ".$_SERVER['PHP_SELF']);
                exit;
            }
            
            // Update user with credentials and activate account
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, password_hash = ?, status = 'active' WHERE id = ?");
            $stmt->execute([$username, $passwordHash, $user_id]);
            
            // Clear the setup session
            unset($_SESSION['show_setup_popup']);
            unset($_SESSION['setup_user_data']);
            
            $_SESSION['success'] = "User account created successfully! The user can now login with username: " . $username;
            
        } catch(PDOException $e) {
            $_SESSION['error'] = "Failed to create user account: " . $e->getMessage();
        }
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function handleRegistration() {
    global $pdo;
    
    $firstName = htmlspecialchars($_POST['first_name']);
    $lastName = htmlspecialchars($_POST['last_name']);
    $email = htmlspecialchars($_POST['email']);
    $contact = htmlspecialchars($_POST['contact_number']);
    $userType = $_POST['user_type'];
    
    // Only gymrat users have membership type
    $membershipType = ($userType === 'gymrat') ? $_POST['membership_type'] : null;
    
    // CHANGED: No auto-generated credentials for gymrat users - they'll setup after admin approval
    if ($userType === 'gymrat') {
        $username = null;
        $passwordHash = null;
        $status = 'pending_payment'; // NEW: Gymrat users start as pending payment
    } else {
        // For admin/trainer, still generate credentials (immediate access)
        $username = generateUsername($firstName, $lastName, $userType);
        $password = generatePassword();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $status = 'active'; // Admin/trainer get immediate access
    }
    
    if ($pdo) {
        try {
            // CHANGED: Insert user with appropriate status
            if ($userType === 'gymrat') {
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, contact_number, user_type, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$firstName, $lastName, $email, $contact, $userType, $status]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, contact_number, user_type, username, password_hash, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$firstName, $lastName, $email, $contact, $userType, $username, $passwordHash, $status]);
            }
            
            $userId = $pdo->lastInsertId();
            
            // Only create membership for gymrat users
            if ($userType === 'gymrat' && $membershipType) {
                $amount = ($membershipType == 'walk-in') ? 60 : 500;
                $stmt = $pdo->prepare("INSERT INTO memberships (user_id, membership_type, amount, start_date) VALUES (?, ?, ?, CURDATE())");
                $stmt->execute([$userId, $membershipType, $amount]);
                
                // Create payment record for gymrat users
                $membershipId = $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO payments (user_id, membership_id, amount, payment_method, status) VALUES (?, ?, ?, 'pending', 'pending')");
                $stmt->execute([$userId, $membershipId, $amount]);
            }
            
            // CHANGED: Different success message for gymrat users
            if ($userType === 'gymrat') {
                $_SESSION['registration_success'] = [
                    'message' => 'Registration successful! Your account is pending payment confirmation. Please proceed to payment and wait for admin confirmation. After payment is confirmed, you will receive a setup link to create your username and password.'
                ];
            } else {
                $_SESSION['registration_success'] = [
                    'username' => $username,
                    'password' => $password,
                    'message' => 'Registration successful! You can now login with your credentials.'
                ];
            }
        } catch(PDOException $e) {
            $_SESSION['error'] = "Registration failed: " . $e->getMessage();
        }
    } else {
        // Fallback for demo
        if ($userType === 'gymrat') {
            $_SESSION['registration_success'] = [
                'message' => 'Registration successful! Please proceed to payment and wait for admin confirmation.'
            ];
        } else {
            $username = generateUsername($firstName, $lastName, $userType);
            $password = generatePassword();
            $_SESSION['registration_success'] = [
                'username' => $username,
                'password' => $password,
                'message' => 'Registration successful! You can now login with your credentials.'
            ];
        }
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// NEW FUNCTION: Handle account setup after payment confirmation
function handleAccountSetup() {
    global $pdo;
    
    $setupToken = $_POST['setup_token'];
    $username = htmlspecialchars($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate passwords match
    if ($password !== $confirmPassword) {
        $_SESSION['error'] = "Passwords do not match!";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    
    // Validate password strength
    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long!";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    
    if ($pdo) {
        try {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = "Username already exists! Please choose a different one.";
                header("Location: ".$_SERVER['PHP_SELF']);
                exit;
            }
            
            // Check if setup token is valid
            $stmt = $pdo->prepare("SELECT user_id FROM account_setup_tokens WHERE token = ? AND used = FALSE AND expires_at > NOW()");
            $stmt->execute([$setupToken]);
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tokenData) {
                $_SESSION['error'] = "Invalid or expired setup token. Please contact admin.";
                header("Location: ".$_SERVER['PHP_SELF']);
                exit;
            }
            
            $userId = $tokenData['user_id'];
            
            // Update user with credentials and activate account
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, password_hash = ?, status = 'active' WHERE id = ?");
            $stmt->execute([$username, $passwordHash, $userId]);
            
            // Mark token as used
            $stmt = $pdo->prepare("UPDATE account_setup_tokens SET used = TRUE WHERE token = ?");
            $stmt->execute([$setupToken]);
            
            // Clear the setup session
            unset($_SESSION['show_setup_form']);
            unset($_SESSION['setup_token']);
            unset($_SESSION['setup_user']);
            
            $_SESSION['success'] = "Account setup completed successfully! You can now login with your credentials.";
            header("Location: index.php");
            exit;
            
        } catch(PDOException $e) {
            $_SESSION['error'] = "Account setup failed: " . $e->getMessage();
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// MODIFIED: Confirm payment and show popup for account setup
function handleConfirmPayment() {
    global $pdo;
    
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        $_SESSION['error'] = "Access denied!";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    
    $payment_id = $_POST['payment_id'];
    $admin_id = $_SESSION['user']['user_id'] ?? 1;
    
    if ($pdo) {
        try {
            $pdo->beginTransaction();
            
            // Get payment details first
            $stmt = $pdo->prepare("SELECT p.*, u.id as user_id, u.first_name, u.last_name, u.email, m.id as membership_id 
                                  FROM payments p 
                                  JOIN users u ON p.user_id = u.id 
                                  JOIN memberships m ON p.membership_id = m.id 
                                  WHERE p.id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                throw new Exception("Payment not found!");
            }
            
            // Update payment status
            $stmt = $pdo->prepare("UPDATE payments SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?");
            $stmt->execute([$admin_id, $payment_id]);
            
            // Update membership payment status
            $stmt = $pdo->prepare("UPDATE memberships SET payment_status = 'confirmed', payment_date = NOW() WHERE id = ?");
            $stmt->execute([$payment['membership_id']]);
            
            // Store user data for the popup form
            $_SESSION['show_setup_popup'] = true;
            $_SESSION['setup_user_data'] = [
                'user_id' => $payment['user_id'],
                'first_name' => $payment['first_name'],
                'last_name' => $payment['last_name'],
                'email' => $payment['email']
            ];
            
            $pdo->commit();
            
            $_SESSION['success'] = "Payment confirmed successfully! Please create username and password for " . $payment['first_name'] . " " . $payment['last_name'];
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Failed to confirm payment: " . $e->getMessage();
        } catch(Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Database connection failed!";
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// UPDATED: Enhanced login function with admin approval check
function handleLogin() {
    global $pdo;
    
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Default credentials for demo (admin/trainer have immediate access)
    $defaultUsers = [
        'admin' => 'admin123',
        'trainer1' => 'trainer123',
        'trainer2' => 'trainer123',
        'trainer3' => 'trainer123'
    ];
    
    if (isset($defaultUsers[$username]) && $defaultUsers[$username] === $password) {
        $_SESSION['user'] = [
            'username' => $username,
            'role' => (strpos($username, 'trainer') !== false) ? 'trainer' : 'admin',
            'first_name' => ucfirst($username),
            'user_id' => ($username === 'admin') ? 1 : (strpos($username, 'trainer') !== false ? substr($username, -1) + 1 : null)
        ];
        $_SESSION['success'] = "Login successful! Welcome back!";
    } else {
        // Check database for gymrat users
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // NEW: Check if gymrat user is active and has completed setup
                    if ($user['user_type'] === 'gymrat') {
                        if ($user['status'] === 'pending_payment') {
                            $_SESSION['error'] = "Your account is pending payment confirmation. Please wait for admin to confirm your payment.";
                            header("Location: ".$_SERVER['PHP_SELF']);
                            exit;
                        } elseif ($user['status'] === 'pending_setup') {
                            $_SESSION['error'] = "Please complete your account setup using the setup link provided after payment confirmation.";
                            header("Location: ".$_SERVER['PHP_SELF']);
                            exit;
                        } elseif ($user['status'] !== 'active') {
                            $_SESSION['error'] = "Your account is not active. Please contact admin.";
                            header("Location: ".$_SERVER['PHP_SELF']);
                            exit;
                        }
                    }
                    
                    // Verify password for active users
                    if (password_verify($password, $user['password_hash'])) {
                        $_SESSION['user'] = [
                            'username' => $user['username'],
                            'role' => $user['user_type'],
                            'first_name' => $user['first_name'],
                            'user_id' => $user['id']
                        ];
                        $_SESSION['success'] = "Login successful! Welcome back " . $user['first_name'] . "!";
                    } else {
                        $_SESSION['error'] = "Invalid credentials!";
                    }
                } else {
                    $_SESSION['error'] = "Invalid credentials!";
                }
            } catch(PDOException $e) {
                $_SESSION['error'] = "Login failed. Please try again.";
            }
        } else {
            $_SESSION['error'] = "Invalid credentials!";
        }
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// NEW FUNCTION: Check if user needs to setup account (for login page)
function checkSetupToken($token) {
    global $pdo;
    
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("
                SELECT u.*, ast.token 
                FROM users u 
                JOIN account_setup_tokens ast ON u.id = ast.user_id 
                WHERE ast.token = ? AND ast.used = FALSE AND ast.expires_at > NOW()
            ");
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return null;
        }
    }
    return null;
}

function handleForgotPassword() {
    $email = $_POST['email'];
    
    $_SESSION['success'] = "If an account with that email exists, a password reset link has been sent.";
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function handleCreateAnnouncement() {
    global $pdo;
    
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        $_SESSION['error'] = "Access denied!";
        return;
    }
    
    $title = htmlspecialchars($_POST['title']);
    $content = htmlspecialchars($_POST['content']);
    $admin_id = $_SESSION['user']['user_id'] ?? 1;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO announcements (admin_id, title, content) VALUES (?, ?, ?)");
        $stmt->execute([$admin_id, $title, $content]);
        $_SESSION['success'] = "Announcement created successfully!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to create announcement: " . $e->getMessage();
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function handleAddComment() {
    global $pdo;
    
    if (!isset($_SESSION['user'])) {
        $_SESSION['error'] = "Please login to comment!";
        return;
    }
    
    $announcement_id = $_POST['announcement_id'];
    $comment_text = htmlspecialchars($_POST['comment_text']);
    $user_id = $_SESSION['user']['user_id'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO comments (announcement_id, user_id, comment_text) VALUES (?, ?, ?)");
        $stmt->execute([$announcement_id, $user_id, $comment_text]);
        $_SESSION['success'] = "Comment added successfully!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to add comment: " . $e->getMessage();
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function handleCheckIn() {
    global $pdo;
    
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        $_SESSION['error'] = "Access denied!";
        return;
    }
    
    $user_id = $_POST['user_id'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO attendance (user_id, check_in) VALUES (?, NOW())");
        $stmt->execute([$user_id]);
        $_SESSION['success'] = "User checked in successfully!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to check in user: " . $e->getMessage();
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function handleCheckOut() {
    global $pdo;
    
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        $_SESSION['error'] = "Access denied!";
        return;
    }
    
    $attendance_id = $_POST['attendance_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE attendance SET check_out = NOW(), duration_minutes = TIMESTAMPDIFF(MINUTE, check_in, NOW()) WHERE id = ?");
        $stmt->execute([$attendance_id]);
        $_SESSION['success'] = "User checked out successfully!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to check out user: " . $e->getMessage();
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function handleActivateUser() {
    global $pdo;
    
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        $_SESSION['error'] = "Access denied!";
        return;
    }
    
    $user_id = $_POST['user_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['success'] = "User activated successfully!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to activate user: " . $e->getMessage();
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function handleRejectUser() {
    global $pdo;
    
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        $_SESSION['error'] = "Access denied!";
        return;
    }
    
    $user_id = $_POST['user_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['success'] = "User registration rejected!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to reject user: " . $e->getMessage();
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function handleCreateWorkoutPlan() {
    global $pdo;
    
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'trainer') {
        $_SESSION['error'] = "Access denied!";
        return;
    }
    
    $client_id = $_POST['client_id'];
    $plan_name = htmlspecialchars($_POST['plan_name']);
    $description = htmlspecialchars($_POST['description']);
    $difficulty = $_POST['difficulty'];
    $trainer_id = $_SESSION['user']['user_id'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO workout_plans (user_id, trainer_id, plan_name, description, difficulty) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$client_id, $trainer_id, $plan_name, $description, $difficulty]);
        
        $workout_plan_id = $pdo->lastInsertId();
        
        // Add exercises if provided
        if (isset($_POST['exercises'])) {
            foreach ($_POST['exercises'] as $exercise) {
                if (!empty($exercise['name'])) {
                    $stmt = $pdo->prepare("INSERT INTO workout_exercises (workout_plan_id, exercise_name, sets, reps, weight, day_of_week) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$workout_plan_id, $exercise['name'], $exercise['sets'], $exercise['reps'], $exercise['weight'], $exercise['day']]);
                }
            }
        }
        
        $_SESSION['success'] = "Workout plan created successfully!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to create workout plan: " . $e->getMessage();
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function handleUpdateClientProgress() {
    global $pdo;
    
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'trainer') {
        $_SESSION['error'] = "Access denied!";
        return;
    }
    
    $client_id = $_POST['client_id'];
    $measurement_type = $_POST['measurement_type'];
    $value = $_POST['value'];
    $notes = htmlspecialchars($_POST['notes'] ?? '');
    
    try {
        $stmt = $pdo->prepare("INSERT INTO progress_tracking (user_id, measurement_type, value, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$client_id, $measurement_type, $value, $notes]);
        $_SESSION['success'] = "Client progress updated successfully!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to update progress: " . $e->getMessage();
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function handleSendMessage() {
    global $pdo;
    
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'trainer') {
        $_SESSION['error'] = "Access denied!";
        return;
    }
    
    $client_id = $_POST['client_id'];
    $message = htmlspecialchars($_POST['message']);
    $trainer_id = $_SESSION['user']['user_id'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, message_type) VALUES (?, ?, ?, 'trainer_to_client')");
        $stmt->execute([$trainer_id, $client_id, $message]);
        $_SESSION['success'] = "Message sent successfully!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to send message: " . $e->getMessage();
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function handleUpdateAvailability() {
    global $pdo;
    
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'trainer') {
        $_SESSION['error'] = "Access denied!";
        return;
    }
    
    $trainer_id = $_SESSION['user']['user_id'];
    
    // Process each day
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    
    try {
        foreach ($days as $day) {
            $is_available = isset($_POST["is_available_{$day}"]) ? 1 : 0;
            $start_time = $_POST["start_time_{$day}"] ?? '09:00';
            $end_time = $_POST["end_time_{$day}"] ?? '17:00';
            
            // Check if availability exists
            $stmt = $pdo->prepare("SELECT id FROM trainer_availability WHERE trainer_id = ? AND day_of_week = ?");
            $stmt->execute([$trainer_id, $day]);
            
            if ($stmt->fetch()) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE trainer_availability SET start_time = ?, end_time = ?, is_available = ? WHERE trainer_id = ? AND day_of_week = ?");
                $stmt->execute([$start_time, $end_time, $is_available, $trainer_id, $day]);
            } else {
                // Insert new
                $stmt = $pdo->prepare("INSERT INTO trainer_availability (trainer_id, day_of_week, start_time, end_time, is_available) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$trainer_id, $day, $start_time, $end_time, $is_available]);
            }
        }
        
        $_SESSION['success'] = "Availability updated successfully!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to update availability: " . $e->getMessage();
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function generateUsername($firstName, $lastName, $userType) {
    $base = strtolower($firstName . '.' . $lastName);
    return $base;
}

function generatePassword() {
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
}

// =============================================================================
// NEW HELPER FUNCTIONS FOR MESSAGING
// =============================================================================

function getUnreadMessageCount($user_id) {
    global $pdo;
    
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread_count 
                FROM messages m 
                LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ? 
                WHERE m.receiver_id = ? AND (ms.is_read = 0 OR ms.id IS NULL)
            ");
            $stmt->execute([$user_id, $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['unread_count'] ?? 0;
        } catch(PDOException $e) {
            return 0;
        }
    }
    return 0;
}

function getMessages($user_id, $conversation_with = null) {
    global $pdo;
    
    if ($pdo) {
        try {
            if ($conversation_with) {
                // Get conversation with specific user
                $stmt = $pdo->prepare("
                    SELECT m.*, 
                           sender.first_name as sender_first_name, 
                           sender.last_name as sender_last_name,
                           receiver.first_name as receiver_first_name,
                           receiver.last_name as receiver_last_name,
                           COALESCE(ms.is_read, 0) as is_read
                    FROM messages m
                    JOIN users sender ON m.sender_id = sender.id
                    JOIN users receiver ON m.receiver_id = receiver.id
                    LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ?
                    WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                       OR (m.sender_id = ? AND m.receiver_id = ?)
                    ORDER BY m.created_at ASC
                ");
                $stmt->execute([$user_id, $user_id, $conversation_with, $conversation_with, $user_id]);
            } else {
                // Get all conversations
                $stmt = $pdo->prepare("
                    SELECT DISTINCT 
                        CASE 
                            WHEN m.sender_id = ? THEN m.receiver_id 
                            ELSE m.sender_id 
                        END as other_user_id,
                        u.first_name,
                        u.last_name,
                        u.user_type,
                        (SELECT message FROM messages 
                         WHERE (sender_id = ? AND receiver_id = other_user_id) 
                            OR (sender_id = other_user_id AND receiver_id = ?)
                         ORDER BY created_at DESC LIMIT 1) as last_message,
                        (SELECT created_at FROM messages 
                         WHERE (sender_id = ? AND receiver_id = other_user_id) 
                            OR (sender_id = other_user_id AND receiver_id = ?)
                         ORDER BY created_at DESC LIMIT 1) as last_message_time,
                        COUNT(CASE WHEN m.receiver_id = ? AND (ms.is_read = 0 OR ms.id IS NULL) THEN 1 END) as unread_count
                    FROM messages m
                    JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = u.id
                    LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ?
                    WHERE m.sender_id = ? OR m.receiver_id = ?
                    GROUP BY other_user_id, u.first_name, u.last_name, u.user_type
                    ORDER BY last_message_time DESC
                ");
                $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return [];
        }
    }
    return [];
}

// =============================================================================
// MAIN CODE EXECUTION
// =============================================================================

// Check if user is logged in
$user = $_SESSION['user'] ?? null;

// NEW: Check for setup token in URL (for gymrat users to setup account after admin approval)
$setupToken = $_GET['setup_token'] ?? null;
$setupUser = null;
$showSetupForm = false;

if ($setupToken && !$user) {
    $setupUser = checkSetupToken($setupToken);
    if ($setupUser) {
        $showSetupForm = true;
        $_SESSION['setup_token'] = $setupToken;
        $_SESSION['setup_user'] = $setupUser;
    } else {
        $_SESSION['error'] = "Invalid or expired setup token.";
    }
}

// NEW: Check if we need to show the admin popup for creating user credentials
$showSetupPopup = $_SESSION['show_setup_popup'] ?? false;
$setupUserData = $_SESSION['setup_user_data'] ?? null;

// NEW: Get unread message count for current user
$unreadMessageCount = 0;
if ($user) {
    $unreadMessageCount = getUnreadMessageCount($user['user_id']);
}

// Fetch data from database
$announcements = [];
$payments = [];
$walkins = [];
$members = [];
$attendance = [];
$trainer_data = [];
$pendingRegistrations = [];
$messages_data = [];
$coaching_sessions = [];
$trainers = [];
$progress_data = [];

if ($pdo) {
    try {
        // Fetch announcements with comments
        $stmt = $pdo->query("
            SELECT a.*, u.first_name, u.last_name 
            FROM announcements a 
            LEFT JOIN users u ON a.admin_id = u.id 
            ORDER BY a.created_at DESC
        ");
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($announcements as &$announcement) {
            $stmt = $pdo->prepare("
                SELECT c.*, u.first_name, u.last_name, u.user_type 
                FROM comments c 
                LEFT JOIN users u ON c.user_id = u.id 
                WHERE c.announcement_id = ? 
                ORDER BY c.created_at ASC
            ");
            $stmt->execute([$announcement['id']]);
            $announcement['comments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Fetch admin data
        if ($user && $user['role'] === 'admin') {
            // Pending registrations (gymrat users waiting for payment)
            $stmt = $pdo->query("
                SELECT u.*, m.membership_type, p.status as payment_status
                FROM users u 
                LEFT JOIN memberships m ON u.id = m.user_id 
                LEFT JOIN payments p ON u.id = p.user_id
                WHERE u.status = 'pending_payment' 
                AND u.user_type = 'gymrat'
                ORDER BY u.created_at DESC
            ");
            $pendingRegistrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Pending payments
            $stmt = $pdo->query("
                SELECT p.*, u.first_name, u.last_name, u.contact_number, m.membership_type 
                FROM payments p 
                JOIN users u ON p.user_id = u.id 
                JOIN memberships m ON p.membership_id = m.id 
                WHERE p.status = 'pending' 
                ORDER BY p.created_at DESC
            ");
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Walk-in users
            $stmt = $pdo->query("
                SELECT u.*, m.membership_type, m.start_date, m.payment_status 
                FROM users u 
                JOIN memberships m ON u.id = m.user_id 
                WHERE m.membership_type = 'walk-in' 
                AND u.user_type = 'gymrat' 
                AND u.status = 'active'
                ORDER BY u.first_name
            ");
            $walkins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Membership users
            $stmt = $pdo->query("
                SELECT u.*, m.membership_type, m.start_date, m.expiration_date, m.payment_status 
                FROM users u 
                JOIN memberships m ON u.id = m.user_id 
                WHERE m.membership_type = 'membership' 
                AND u.user_type = 'gymrat' 
                AND u.status = 'active'
                ORDER BY u.first_name
            ");
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Attendance records
            $stmt = $pdo->query("
                SELECT a.*, u.first_name, u.last_name, u.contact_number 
                FROM attendance a 
                JOIN users u ON a.user_id = u.id 
                ORDER BY a.check_in DESC 
                LIMIT 50
            ");
            $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Fetch trainer data
        if ($user && $user['role'] === 'trainer') {
            $trainer_id = $user['user_id'];
            
            // Fetch trainer's clients (all active gymrat users for demo)
            $stmt = $pdo->prepare("
                SELECT u.*, m.membership_type, m.payment_status 
                FROM users u 
                JOIN memberships m ON u.id = m.user_id 
                WHERE u.user_type = 'gymrat' 
                AND u.status = 'active'
                ORDER BY u.first_name
            ");
            $stmt->execute();
            $trainer_data['clients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch trainer's appointments
            $stmt = $pdo->prepare("
                SELECT cs.*, u.first_name, u.last_name, u.contact_number 
                FROM coaching_services cs 
                JOIN users u ON cs.user_id = u.id 
                WHERE cs.assigned_trainer_id = ? 
                ORDER BY cs.appointment_date DESC
            ");
            $stmt->execute([$trainer_id]);
            $trainer_data['appointments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch workout plans created by trainer
            $stmt = $pdo->prepare("
                SELECT wp.*, u.first_name, u.last_name 
                FROM workout_plans wp 
                JOIN users u ON wp.user_id = u.id 
                WHERE wp.trainer_id = ? 
                ORDER BY wp.created_at DESC
            ");
            $stmt->execute([$trainer_id]);
            $trainer_data['workout_plans'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch client progress
            $stmt = $pdo->prepare("
                SELECT pt.*, u.first_name, u.last_name 
                FROM progress_tracking pt 
                JOIN users u ON pt.user_id = u.id 
                WHERE u.id IN (
                    SELECT user_id FROM coaching_services WHERE assigned_trainer_id = ?
                ) OR u.user_type = 'gymrat'
                ORDER BY pt.measured_at DESC
                LIMIT 20
            ");
            $stmt->execute([$trainer_id]);
            $trainer_data['client_progress'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // NEW: Fetch data for gymrat users
        if ($user && $user['role'] === 'gymrat') {
            $user_id = $user['user_id'];
            
            // Fetch user's coaching sessions
            $stmt = $pdo->prepare("
                SELECT cs.*, t.first_name as trainer_first_name, t.last_name as trainer_last_name 
                FROM coaching_services cs 
                LEFT JOIN users t ON cs.assigned_trainer_id = t.id 
                WHERE cs.user_id = ? 
                ORDER BY cs.appointment_date DESC
            ");
            $stmt->execute([$user_id]);
            $coaching_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch user's progress
            $stmt = $pdo->prepare("
                SELECT * FROM progress_tracking 
                WHERE user_id = ? 
                ORDER BY measured_at DESC
            ");
            $stmt->execute([$user_id]);
            $progress_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch available trainers
            $stmt = $pdo->query("
                SELECT * FROM users 
                WHERE user_type = 'trainer' AND status = 'active'
                ORDER BY first_name
            ");
            $trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // NEW: Fetch messages for all logged-in users
        if ($user) {
            $conversation_with = $_GET['conversation_with'] ?? null;
            $messages_data = getMessages($user['user_id'], $conversation_with);
        }
        
    } catch(PDOException $e) {
        // Use sample data if database fails
        $announcements = [
            [
                'title' => 'YOUR TRANSFORMATION STARTS HERE!',
                'content' => '<p>Welcome to our gym! We are excited to help you achieve your fitness goals.</p>',
                'comments' => []
            ]
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signus - Gym Monitoring System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a2a3a;
            --secondary: #e74c3c;
            --accent: #3498db;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --gold: #f1c40f;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }
        
        body {
            background-color: #0d1419;
            color: var(--light);
            overflow-x: hidden;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Styles */
        header {
            background: rgba(26, 42, 58, 0.95);
            color: white;
            padding: 15px 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            font-size: 32px;
            font-weight: 700;
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
        }
        
        .logo-special {
            font-family: 'Times New Roman', serif;
            font-size: 42px;
            margin-right: 5px;
            color: var(--gold);
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            transform: rotate(-5deg) scale(1.2);
            display: inline-block;
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 25px;
            position: relative;
        }
        
        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            position: relative;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        nav ul li a:hover {
            color: var(--gold);
        }
        
        nav ul li a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background-color: var(--gold);
            transition: width 0.3s;
        }
        
        nav ul li a:hover::after {
            width: 100%;
        }
        
        .auth-buttons button {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-left: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 14px;
        }
        
        .auth-buttons button:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        /* NEW: Unread Message Badge */
        .message-badge {
            background: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            margin-left: 5px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* NEW: Messaging System Styles */
        .messages-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            height: 600px;
        }
        
        .conversations-list {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            overflow-y: auto;
        }
        
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .conversation-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .conversation-item.active {
            background: rgba(52, 152, 219, 0.2);
            border-left: 3px solid var(--accent);
        }
        
        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .conversation-name {
            font-weight: 600;
            color: var(--light);
        }
        
        .conversation-time {
            font-size: 12px;
            color: #999;
        }
        
        .conversation-preview {
            font-size: 14px;
            color: #bbb;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-unread {
            background: var(--danger);
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 12px;
        }
        
        .chat-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px 10px 0 0;
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .message {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 15px;
            position: relative;
        }
        
        .message-sent {
            align-self: flex-end;
            background: var(--accent);
            border-bottom-right-radius: 5px;
        }
        
        .message-received {
            align-self: flex-start;
            background: rgba(255, 255, 255, 0.1);
            border-bottom-left-radius: 5px;
        }
        
        .message-sender {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .message-sent .message-sender {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .message-received .message-sender {
            color: var(--gold);
        }
        
        .message-content {
            margin-bottom: 5px;
        }
        
        .message-time {
            font-size: 11px;
            text-align: right;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .chat-input {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .message-form {
            display: flex;
            gap: 10px;
        }
        
        .message-input {
            flex: 1;
            padding: 12px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.05);
            color: white;
        }
        
        .send-message-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .send-message-btn:hover {
            background: #2980b9;
        }
        
        /* NEW: Coaching Booking Styles */
        .coaching-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .coaching-type-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .coaching-type-card:hover {
            border-color: var(--accent);
            transform: translateY(-5px);
        }
        
        .coaching-type-card.selected {
            border-color: var(--gold);
            background: rgba(241, 196, 15, 0.1);
        }
        
        .coaching-icon {
            font-size: 40px;
            color: var(--gold);
            margin-bottom: 15px;
        }
        
        .coaching-sessions-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .session-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid var(--accent);
        }
        
        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .session-trainer {
            font-weight: 600;
            color: var(--accent);
        }
        
        .session-date {
            color: var(--gold);
            font-weight: 600;
        }
        
        .session-type {
            background: rgba(52, 152, 219, 0.2);
            color: var(--accent);
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
        }
        
        /* NEW: Progress Tracking Styles */
        .progress-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .progress-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .progress-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--gold);
            margin: 10px 0;
        }
        
        .progress-label {
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 14px;
        }
        
        /* NEW: Upgrade Banner for Walk-in Users */
        .upgrade-banner {
            background: linear-gradient(135deg, var(--gold), #e67e22);
            color: var(--dark);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        
        .upgrade-banner h3 {
            margin-bottom: 10px;
            font-family: 'Oswald', sans-serif;
        }
        
        .upgrade-btn {
            background: var(--dark);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .upgrade-btn:hover {
            background: #2c3e50;
            transform: translateY(-2px);
        }

        /* REST OF YOUR ORIGINAL STYLES */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            height: 100vh;
            display: flex;
            align-items: center;
            color: white;
            text-align: center;
            margin-top: 60px;
            position: relative;
        }
        
        .hero-content {
            max-width: 900px;
            margin: 0 auto;
            opacity: 0;
            transform: translateY(30px);
            animation: fadeUp 1s forwards 0.5s;
            z-index: 2;
            position: relative;
        }
        
        .hero h1 {
            font-size: 58px;
            margin-bottom: 20px;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.7);
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            letter-spacing: 2px;
            line-height: 1.2;
        }
        
        .hero h1 span {
            color: var(--gold);
        }
        
        .hero p {
            font-size: 20px;
            margin-bottom: 30px;
            line-height: 1.6;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .cta-button {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 18px 45px;
            border-radius: 30px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 20px;
        }
        
        .cta-button:hover {
            background: #c0392b;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
        }
        
        /* Announcements Section */
        .announcements {
            padding: 100px 0;
            background-color: #0f171f;
        }
        
        .announcement-card {
            background: rgba(26, 42, 58, 0.8);
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .announcement-title {
            font-size: 24px;
            color: var(--gold);
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
        }
        
        .announcement-date {
            color: #999;
            font-size: 14px;
        }
        
        .announcement-content {
            margin-bottom: 25px;
            line-height: 1.7;
        }
        
        .comments-section {
            margin-top: 25px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }
        
        .comment {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .comment-author {
            font-weight: 600;
            color: var(--gold);
        }
        
        .comment-date {
            color: #999;
            font-size: 12px;
        }
        
        .comment-form {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .comment-input {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.05);
            color: white;
        }
        
        .comment-btn {
            background: var(--gold);
            color: var(--dark);
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        
        /* Membership Perks Section */
        .perks {
            padding: 100px 0;
            background: linear-gradient(135deg, #0a0f14, #151e28);
        }
        
        .perks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        
        .perk-card {
            background: rgba(26, 42, 58, 0.8);
            border-radius: 15px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            transition: all 0.4s;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .perk-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            border-color: var(--gold);
        }
        
        .perk-icon {
            font-size: 50px;
            color: var(--gold);
            margin-bottom: 20px;
        }
        
        .perk-card h3 {
            font-size: 22px;
            margin-bottom: 15px;
            color: var(--light);
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
        }
        
        .perk-card p {
            color: #bbb;
            line-height: 1.7;
        }
        
        /* Features Section */
        .features {
            padding: 100px 0;
            background-color: #0f171f;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 60px;
            position: relative;
        }
        
        .section-title h2 {
            font-size: 42px;
            color: var(--light);
            display: inline-block;
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .section-title h2::after {
            content: '';
            position: absolute;
            width: 100px;
            height: 4px;
            background: var(--gold);
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .feature-card {
            background: rgba(26, 42, 58, 0.7);
            border-radius: 10px;
            padding: 40px 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: all 0.4s;
            text-align: center;
            opacity: 0;
            transform: translateY(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .feature-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border-color: var(--gold);
        }
        
        .feature-icon {
            font-size: 60px;
            color: var(--gold);
            margin-bottom: 25px;
            text-shadow: 0 0 10px rgba(241, 196, 15, 0.3);
        }
        
        .feature-card h3 {
            font-size: 26px;
            margin-bottom: 20px;
            color: var(--light);
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .feature-card p {
            color: #bbb;
            line-height: 1.7;
        }
        
        /* Registration Section */
        .registration {
            padding: 100px 0;
            background: linear-gradient(135deg, #0a0f14, #151e28);
            position: relative;
            overflow: hidden;
        }
        
        .registration::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            opacity: 0.1;
            z-index: 0;
        }
        
        .registration .container {
            position: relative;
            z-index: 1;
        }
        
        .registration-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        
        .option-card {
            background: rgba(26, 42, 58, 0.8);
            border-radius: 15px;
            padding: 50px 30px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            transition: all 0.4s;
            cursor: pointer;
            opacity: 0;
            transform: translateY(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }
        
        .option-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--gold);
            transform: scaleX(0);
            transition: transform 0.4s;
        }
        
        .option-card:hover::before {
            transform: scaleX(1);
        }
        
        .option-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }
        
        .option-icon {
            font-size: 70px;
            margin-bottom: 25px;
            color: var(--gold);
            text-shadow: 0 0 15px rgba(241, 196, 15, 0.3);
        }
        
        .option-card h3 {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--light);
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .option-card p {
            color: #bbb;
            margin-bottom: 30px;
            line-height: 1.7;
        }
        
        .option-button {
            background: transparent;
            color: var(--gold);
            border: 2px solid var(--gold);
            padding: 14px 35px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 15px;
        }
        
        .option-button:hover {
            background: var(--gold);
            color: var(--dark);
            box-shadow: 0 0 20px rgba(241, 196, 15, 0.4);
        }
        
        /* Payment Section */
        .payment-info {
            background: rgba(26, 42, 58, 0.9);
            padding: 80px 0;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .payment-info h2 {
            font-size: 36px;
            margin-bottom: 30px;
            color: var(--light);
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .payment-methods {
            display: flex;
            justify-content: center;
            gap: 50px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        .payment-method {
            background: rgba(255, 255, 255, 0.05);
            padding: 30px;
            border-radius: 10px;
            width: 250px;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .payment-method:hover {
            transform: translateY(-10px);
            border-color: var(--gold);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .payment-method i {
            font-size: 50px;
            color: var(--gold);
            margin-bottom: 20px;
        }
        
        .payment-method h3 {
            font-size: 22px;
            margin-bottom: 15px;
            color: var(--light);
        }
        
        .payment-method p {
            color: #bbb;
            font-size: 18px;
            font-weight: 600;
        }
        
        /* Footer */
        footer {
            background: #0a0f14;
            color: white;
            padding: 80px 0 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-column h3 {
            font-size: 22px;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
            color: var(--light);
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .footer-column h3::after {
            content: '';
            position: absolute;
            width: 50px;
            height: 3px;
            background: var(--gold);
            bottom: 0;
            left: 0;
        }
        
        .footer-column p, .footer-column a {
            color: #999;
            line-height: 1.8;
            margin-bottom: 12px;
            display: block;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-column a:hover {
            color: var(--gold);
        }
        
        .social-icons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .social-icons a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            transition: all 0.3s;
            font-size: 18px;
        }
        
        .social-icons a:hover {
            background: var(--gold);
            color: var(--dark);
            transform: translateY(-5px);
        }
        
        .copyright {
            text-align: center;
            padding-top: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            color: #777;
            font-size: 14px;
        }
        
        /* Animations */
        @keyframes fadeUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: #1a2a3a;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            animation: modalFade 0.4s;
        }
        
        @keyframes modalFade {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: var(--gold);
        }
        
        .modal h2 {
            margin-bottom: 25px;
            text-align: center;
            color: var(--light);
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #ccc;
            font-weight: 500;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 14px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.05);
            color: white;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 10px rgba(241, 196, 15, 0.2);
        }
        
        .submit-btn {
            background: var(--gold);
            color: var(--dark);
            border: none;
            padding: 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
        }
        
        .submit-btn:hover {
            background: #e6b90d;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 500;
        }
        
        .alert-success {
            background: var(--success);
            color: white;
        }
        
        .alert-error {
            background: var(--danger);
            color: white;
        }
        
        .alert-info {
            background: var(--accent);
            color: white;
        }
        
        .user-welcome {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
            border-left: 4px solid var(--gold);
        }

        /* ADMIN DASHBOARD STYLES */
        .admin-dashboard {
            margin-top: 80px;
            padding: 20px 0;
            background: #0f171f;
            min-height: calc(100vh - 80px);
        }
        
        .admin-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            align-items: start;
        }
        
        .admin-sidebar {
            background: rgba(26, 42, 58, 0.9);
            border-radius: 15px;
            padding: 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 100px;
            height: fit-content;
        }
        
        .admin-profile {
            padding: 30px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .admin-profile h3 {
            color: var(--gold);
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .admin-avatar {
            width: 50px;
            height: 50px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--dark);
        }
        
        .admin-details {
            text-align: left;
            flex: 1;
        }
        
        .admin-details strong {
            display: block;
            color: var(--light);
            font-size: 16px;
        }
        
        .admin-details span {
            color: #999;
            font-size: 14px;
        }
        
        .admin-sidebar-nav {
            padding: 20px 0;
        }
        
        .sidebar-btn {
            display: flex;
            align-items: center;
            gap: 15px;
            width: 100%;
            padding: 15px 25px;
            background: transparent;
            border: none;
            color: var(--light);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
            text-align: left;
        }
        
        .sidebar-btn i {
            width: 20px;
            text-align: center;
            color: var(--gold);
        }
        
        .sidebar-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--gold);
        }
        
        .sidebar-btn.active {
            background: rgba(241, 196, 15, 0.1);
            color: var(--gold);
            border-right: 3px solid var(--gold);
        }
        
        .admin-content {
            background: rgba(26, 42, 58, 0.8);
            border-radius: 15px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            min-height: 600px;
        }
        
        .admin-section {
            display: none;
        }
        
        .admin-section.active {
            display: block;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .section-header h2 {
            color: var(--gold);
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 28px;
        }
        
        .admin-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .payment-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--gold);
            font-size: 18px;
        }
        
        .user-contact {
            color: #999;
            margin-top: 5px;
        }
        
        .payment-details {
            text-align: right;
        }
        
        .payment-amount {
            font-size: 20px;
            font-weight: 600;
            color: var(--success);
        }
        
        .payment-method {
            color: #999;
            margin-top: 5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-confirm {
            background: var(--success);
            color: white;
        }
        
        .btn-confirm:hover {
            background: #27ae60;
        }
        
        .btn-checkin {
            background: var(--accent);
            color: white;
        }
        
        .btn-checkin:hover {
            background: #2980b9;
        }
        
        .btn-checkout {
            background: var(--warning);
            color: white;
        }
        
        .btn-checkout:hover {
            background: #e67e22;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .admin-table th,
        .admin-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .admin-table th {
            background: rgba(255, 255, 255, 0.1);
            color: var(--gold);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 14px;
        }
        
        .admin-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .status-pending {
            color: var(--warning);
            font-weight: 600;
        }
        
        .status-confirmed {
            color: var(--success);
            font-weight: 600;
        }
        
        .status-active {
            color: var(--success);
            font-weight: 600;
        }
        
        /* TRAINER DASHBOARD STYLES */
        .trainer-dashboard {
            margin-top: 80px;
            padding: 20px 0;
            background: #0f171f;
            min-height: calc(100vh - 80px);
        }
        
        .trainer-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            align-items: start;
        }
        
        .trainer-sidebar {
            background: rgba(26, 42, 58, 0.9);
            border-radius: 15px;
            padding: 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 100px;
            height: fit-content;
        }
        
        .trainer-profile {
            padding: 30px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .trainer-profile h3 {
            color: var(--accent);
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .trainer-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .trainer-avatar {
            width: 50px;
            height: 50px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
        
        .trainer-details {
            text-align: left;
            flex: 1;
        }
        
        .trainer-details strong {
            display: block;
            color: var(--light);
            font-size: 16px;
        }
        
        .trainer-details span {
            color: #999;
            font-size: 14px;
        }
        
        .trainer-sidebar-nav {
            padding: 20px 0;
        }
        
        .trainer-sidebar-btn {
            display: flex;
            align-items: center;
            gap: 15px;
            width: 100%;
            padding: 15px 25px;
            background: transparent;
            border: none;
            color: var(--light);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
            text-align: left;
        }
        
        .trainer-sidebar-btn i {
            width: 20px;
            text-align: center;
            color: var(--accent);
        }
        
        .trainer-sidebar-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--accent);
        }
        
        .trainer-sidebar-btn.active {
            background: rgba(52, 152, 219, 0.1);
            color: var(--accent);
            border-right: 3px solid var(--accent);
        }
        
        .trainer-content {
            background: rgba(26, 42, 58, 0.8);
            border-radius: 15px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            min-height: 600px;
        }
        
        .trainer-section {
            display: none;
        }
        
        .trainer-section.active {
            display: block;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 25px 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent);
        }
        
        .stat-icon {
            font-size: 40px;
            color: var(--accent);
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--light);
            margin: 10px 0;
        }
        
        .stat-label {
            color: #999;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .client-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }
        
        .client-card:hover {
            border-color: var(--accent);
            transform: translateX(5px);
        }
        
        .client-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .client-name {
            font-weight: 600;
            color: var(--accent);
            font-size: 18px;
        }
        
        .client-contact {
            color: #999;
            font-size: 14px;
        }
        
        .appointment-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid var(--accent);
        }
        
        .appointment-time {
            color: var(--accent);
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .appointment-client {
            color: var(--light);
            font-weight: 500;
        }
        
        .appointment-service {
            color: #999;
            font-size: 14px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-scheduled {
            background: rgba(52, 152, 219, 0.2);
            color: var(--accent);
        }
        
        .status-completed {
            background: rgba(46, 204, 113, 0.2);
            color: var(--success);
        }
        
        .status-cancelled {
            background: rgba(231, 76, 60, 0.2);
            color: var(--danger);
        }
        
        .message-bubble {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            max-width: 80%;
        }
        
        .message-sent {
            background: rgba(52, 152, 219, 0.2);
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }
        
        .message-received {
            background: rgba(255, 255, 255, 0.1);
            margin-right: auto;
            border-bottom-left-radius: 5px;
        }
        
        .message-sender {
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 5px;
        }
        
        .message-time {
            color: #999;
            font-size: 12px;
            text-align: right;
        }
        
        .calendar-day {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .calendar-day.available {
            border-color: var(--success);
            background: rgba(46, 204, 113, 0.1);
        }
        
        .calendar-day.unavailable {
            border-color: var(--danger);
            background: rgba(231, 76, 60, 0.1);
        }

        /* NEW: Admin Setup Popup Modal Styles */
        .setup-popup-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }
        
        .setup-popup-modal.active {
            display: flex;
        }
        
        .setup-popup-content {
            background: #1a2a3a;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            border: 2px solid var(--gold);
            position: relative;
            animation: modalFade 0.4s;
        }
        
        .setup-popup-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .setup-popup-header h2 {
            color: var(--gold);
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .user-info-box {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid var(--gold);
        }
        
        .user-info-box h3 {
            color: var(--gold);
            margin-bottom: 10px;
        }
        
        .setup-instructions {
            background: rgba(241, 196, 15, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid var(--gold);
        }
        
        .setup-instructions h4 {
            color: var(--gold);
            margin-bottom: 10px;
        }
        
        /* User Setup Modal Styles */
        .setup-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }
        
        .setup-modal.active {
            display: flex;
        }
        
        .setup-content {
            background: #1a2a3a;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            border: 2px solid var(--gold);
            position: relative;
            animation: modalFade 0.4s;
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .setup-header h2 {
            color: var(--gold);
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .user-welcome-info {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid var(--gold);
        }
        
        .user-welcome-info h3 {
            color: var(--gold);
            margin-bottom: 10px;
        }

        /* Responsive styles */
        @media (max-width: 968px) {
            .admin-layout,
            .trainer-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .admin-sidebar,
            .trainer-sidebar {
                position: static;
            }
            
            .admin-sidebar-nav,
            .trainer-sidebar-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                padding: 20px;
            }
            
            .sidebar-btn,
            .trainer-sidebar-btn {
                flex: 1;
                min-width: 150px;
                justify-content: center;
                border-radius: 8px;
            }
            
            .sidebar-btn.active {
                border-right: none;
                border-bottom: 3px solid var(--gold);
            }
            
            .trainer-sidebar-btn.active {
                border-right: none;
                border-bottom: 3px solid var(--accent);
            }
            
            .messages-container {
                grid-template-columns: 1fr;
                height: auto;
            }
        }
        
        @media (max-width: 768px) {
            .payment-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .payment-details {
                text-align: left;
                width: 100%;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: space-between;
            }
            
            .hero h1 {
                font-size: 36px;
            }
            
            .hero p {
                font-size: 18px;
            }
            
            .section-title h2 {
                font-size: 32px;
            }
            
            nav ul {
                display: none;
            }
            
            .coaching-types {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container header-container">
            <div class="logo">
                <span class="logo-special">S</span>IGNUS
            </div>
            <nav>
                <ul>
                    <li><a href="#home">Home</a></li>
                    <li><a href="#announcements">Announcements</a></li>
                    <li><a href="#perks">Membership Perks</a></li>
                    <li><a href="#registration">Registration</a></li>
                    <li><a href="#payment">Payment</a></li>
                    <?php if($user && $user['role'] === 'admin'): ?>
                        <li><a href="#admin-dashboard">Admin Dashboard</a></li>
                    <?php endif; ?>
                    <?php if($user && $user['role'] === 'trainer'): ?>
                        <li><a href="#trainer-dashboard">Trainer Dashboard</a></li>
                    <?php endif; ?>
                    <?php if($user && $user['role'] === 'gymrat'): ?>
                        <li><a href="#member-dashboard">Member Dashboard</a></li>
                    <?php endif; ?>
                    <!-- NEW: Messages Link for All Users -->
                    <?php if($user): ?>
                        <li>
                            <a href="#messages">
                                <i class="fas fa-envelope"></i> Messages
                                <?php if($unreadMessageCount > 0): ?>
                                    <span class="message-badge"><?= $unreadMessageCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if($user): ?>
                        <li><a href="?logout=1">Logout (<?= $user['first_name'] ?>)</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="auth-buttons">
                <?php if(!$user): ?>
                    <button onclick="openModal('loginModal')">Login</button>
                    <button onclick="openModal('registerModal')">Register</button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Display Messages and User Welcome -->
    <div class="container">
        <!-- Display Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?= $_SESSION['error'] ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['info'])): ?>
            <div class="alert alert-info"><?= $_SESSION['info'] ?></div>
            <?php unset($_SESSION['info']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['registration_success'])): ?>
            <div class="alert alert-success">
                <h3>Registration Successful!</h3>
                <?php if(isset($_SESSION['registration_success']['username'])): ?>
                    <p>Username: <strong><?= $_SESSION['registration_success']['username'] ?></strong></p>
                    <p>Password: <strong><?= $_SESSION['registration_success']['password'] ?></strong></p>
                <?php endif; ?>
                <p><?= $_SESSION['registration_success']['message'] ?></p>
            </div>
            <?php unset($_SESSION['registration_success']); ?>
        <?php endif; ?>

        <?php if($user): ?>
            <div class="user-welcome">
                <h3>Welcome, <?= $user['first_name'] ?>!</h3>
                <p>You are logged in as <?= $user['role'] ?>. Enjoy your workout!</p>
                <?php if($unreadMessageCount > 0): ?>
                    <p>You have <strong><?= $unreadMessageCount ?></strong> unread message(s). <a href="#messages" style="color: var(--gold);">View messages</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- NEW: Admin Setup Popup Modal (appears after payment confirmation) -->
    <div class="setup-popup-modal <?= $showSetupPopup ? 'active' : '' ?>" id="adminSetupPopup">
        <div class="setup-popup-content">
            <div class="setup-popup-header">
                <h2>Create User Account</h2>
                <p class="subtitle">Setup username and password for the user</p>
            </div>
            
            <?php if($setupUserData): ?>
            <div class="user-info-box">
                <h3>User Information</h3>
                <p><strong>Name:</strong> <?= $setupUserData['first_name'] ?> <?= $setupUserData['last_name'] ?></p>
                <p><strong>Email:</strong> <?= $setupUserData['email'] ?></p>
                <p><strong>Status:</strong> Payment Confirmed ✅</p>
            </div>
            
            <div class="setup-instructions">
                <h4>📝 Account Setup</h4>
                <p>Create a username and password for this user. They will use these credentials to login to their account.</p>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_user_credentials">
                <input type="hidden" name="user_id" value="<?= $setupUserData['user_id'] ?>">
                
                <div class="form-group">
                    <label for="adminUsername">Username</label>
                    <input type="text" id="adminUsername" name="username" required placeholder="Enter username">
                    <small style="color: #999; display: block; margin-top: 5px;">This will be the user's login username</small>
                </div>
                
                <div class="form-group">
                    <label for="adminPassword">Password</label>
                    <input type="password" id="adminPassword" name="password" required placeholder="Enter password">
                    <small style="color: #999; display: block; margin-top: 5px;">Minimum 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="adminConfirmPassword">Confirm Password</label>
                    <input type="password" id="adminConfirmPassword" name="confirm_password" required placeholder="Confirm password">
                </div>
                
                <button type="submit" class="submit-btn">Create User Account</button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <p style="color: #999; font-size: 14px;">
                    The user will be able to login immediately after account creation.
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- User Setup Modal (for users with setup tokens) -->
    <div class="setup-modal <?= $showSetupForm ? 'active' : '' ?>" id="accountSetupModal">
        <div class="setup-content">
            <div class="setup-header">
                <h2>Complete Your Account Setup</h2>
                <p class="subtitle">Create your username and password to activate your account</p>
            </div>
            
            <?php if($setupUser): ?>
            <div class="user-welcome-info">
                <h3>Welcome, <?= $setupUser['first_name'] ?> <?= $setupUser['last_name'] ?>! 🎉</h3>
                <p><strong>Email:</strong> <?= $setupUser['email'] ?></p>
                <p>Your payment has been confirmed. Please create your login credentials below.</p>
            </div>
            
            <div class="setup-instructions">
                <h4>📝 Setup Instructions</h4>
                <p>Choose a unique username and secure password to complete your account activation.</p>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="setup_account">
                <input type="hidden" name="setup_token" value="<?= $setupToken ?>">
                
                <div class="form-group">
                    <label for="setupUsername">Choose Username</label>
                    <input type="text" id="setupUsername" name="username" required placeholder="Enter your username">
                    <small style="color: #999; display: block; margin-top: 5px;">This will be your login username</small>
                </div>
                
                <div class="form-group">
                    <label for="setupPassword">Password</label>
                    <input type="password" id="setupPassword" name="password" required placeholder="Enter your password">
                    <small style="color: #999; display: block; margin-top: 5px;">Minimum 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="setupConfirmPassword">Confirm Password</label>
                    <input type="password" id="setupConfirmPassword" name="confirm_password" required placeholder="Confirm your password">
                </div>
                
                <button type="submit" class="submit-btn">Complete Account Setup</button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <p style="color: #999; font-size: 14px;">
                    Having trouble? <a href="mailto:admin@signusgym.com" style="color: var(--gold);">Contact support</a>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- NEW: Member Dashboard Section -->
    <?php if($user && $user['role'] === 'gymrat'): ?>
    <section class="admin-dashboard" id="member-dashboard">
        <div class="container">
            <div class="admin-layout">
                <!-- Sidebar Navigation -->
                <div class="admin-sidebar">
                    <div class="admin-profile">
                        <h3>Member Panel</h3>
                        <div class="admin-info">
                            <div class="admin-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="admin-details">
                                <strong><?= $user['first_name'] ?></strong>
                                <span>Gym Member</span>
                            </div>
                        </div>
                    </div>
                    <nav class="admin-sidebar-nav">
                        <button class="sidebar-btn active" onclick="showMemberSection('profile')">
                            <i class="fas fa-user"></i>
                            <span>My Profile</span>
                        </button>
                        <button class="sidebar-btn" onclick="showMemberSection('coaching')">
                            <i class="fas fa-dumbbell"></i>
                            <span>Coaching</span>
                        </button>
                        <button class="sidebar-btn" onclick="showMemberSection('progress')">
                            <i class="fas fa-chart-line"></i>
                            <span>My Progress</span>
                        </button>
                        <button class="sidebar-btn" onclick="showMemberSection('messages')">
                            <i class="fas fa-envelope"></i>
                            <span>Messages</span>
                            <?php if($unreadMessageCount > 0): ?>
                                <span class="message-badge"><?= $unreadMessageCount ?></span>
                            <?php endif; ?>
                        </button>
                        <!-- Upgrade Banner for Walk-in Users -->
                        <?php 
                        $user_membership = null;
                        foreach($members as $member) {
                            if ($member['id'] == $user['user_id']) {
                                $user_membership = $member;
                                break;
                            }
                        }
                        foreach($walkins as $walkin) {
                            if ($walkin['id'] == $user['user_id']) {
                                $user_membership = $walkin;
                                break;
                            }
                        }
                        ?>
                        <?php if($user_membership && $user_membership['membership_type'] === 'walk-in'): ?>
                        <div class="upgrade-banner" style="margin: 20px; padding: 15px;">
                            <h4>Upgrade to Membership!</h4>
                            <p>Get full access to all features</p>
                            <button class="upgrade-btn" onclick="openModal('upgradeModal')">Upgrade Now</button>
                        </div>
                        <?php endif; ?>
                    </nav>
                </div>

                <!-- Main Content Area -->
                <div class="admin-content">
                    <!-- Member Profile Section -->
                    <div class="admin-section active" id="member-profile-section">
                        <div class="section-header">
                            <h2>Member Profile</h2>
                        </div>
                        <div class="admin-card">
                            <h3>Welcome, <?= $user['first_name'] ?>!</h3>
                            <p><strong>Username:</strong> <?= $user['username'] ?></p>
                            <p><strong>Role:</strong> Gym Member</p>
                            <p><strong>Membership Type:</strong> 
                                <span class="status-<?= $user_membership['membership_type'] === 'membership' ? 'confirmed' : 'pending' ?>">
                                    <?= ucfirst($user_membership['membership_type'] ?? 'Not specified') ?>
                                </span>
                            </p>
                            <?php if($user_membership && $user_membership['membership_type'] === 'membership' && $user_membership['expiration_date']): ?>
                                <p><strong>Membership Expires:</strong> <?= date('M j, Y', strtotime($user_membership['expiration_date'])) ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-dumbbell"></i>
                                </div>
                                <div class="stat-number"><?= count($coaching_sessions) ?></div>
                                <div class="stat-label">Coaching Sessions</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="stat-number"><?= count($progress_data) ?></div>
                                <div class="stat-label">Progress Records</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="stat-number"><?= $unreadMessageCount ?></div>
                                <div class="stat-label">Unread Messages</div>
                            </div>
                        </div>
                    </div>

                    <!-- Coaching Section -->
                    <div class="admin-section" id="member-coaching-section">
                        <div class="section-header">
                            <h2>Coaching Sessions</h2>
                            <button class="btn btn-confirm" onclick="openModal('bookCoachingModal')">
                                <i class="fas fa-plus"></i> Book Session
                            </button>
                        </div>
                        
                        <?php if(empty($coaching_sessions)): ?>
                            <div class="admin-card" style="text-align: center; padding: 40px;">
                                <i class="fas fa-dumbbell" style="font-size: 50px; color: var(--accent); margin-bottom: 20px;"></i>
                                <h3>No Coaching Sessions</h3>
                                <p>You haven't booked any coaching sessions yet.</p>
                                <button class="btn btn-confirm" onclick="openModal('bookCoachingModal')" style="margin-top: 15px;">
                                    Book Your First Session
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="coaching-sessions-list">
                                <?php foreach($coaching_sessions as $session): ?>
                                <div class="session-card">
                                    <div class="session-header">
                                        <div class="session-trainer">
                                            <i class="fas fa-user-tie"></i>
                                            <?= $session['trainer_first_name'] ?> <?= $session['trainer_last_name'] ?>
                                        </div>
                                        <div class="session-date">
                                            <i class="fas fa-calendar"></i>
                                            <?= date('M j, Y g:i A', strtotime($session['appointment_date'])) ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <span class="session-type"><?= ucfirst($session['service_type']) ?></span>
                                            <span class="status-badge status-<?= $session['status'] ?>" style="margin-left: 10px;">
                                                <?= ucfirst($session['status']) ?>
                                            </span>
                                        </div>
                                        <?php if($session['status'] === 'scheduled'): ?>
                                            <button class="btn" style="background: var(--danger); color: white; padding: 5px 10px; font-size: 12px;">
                                                Cancel
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if($session['notes']): ?>
                                        <p style="margin-top: 10px; color: #ccc; font-size: 14px;">
                                            <strong>Notes:</strong> <?= $session['notes'] ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Progress Section -->
                    <div class="admin-section" id="member-progress-section">
                        <div class="section-header">
                            <h2>My Progress</h2>
                        </div>
                        
                        <?php if(empty($progress_data)): ?>
                            <div class="admin-card" style="text-align: center; padding: 40px;">
                                <i class="fas fa-chart-line" style="font-size: 50px; color: var(--accent); margin-bottom: 20px;"></i>
                                <h3>No Progress Records</h3>
                                <p>Your progress tracking will appear here once your trainer starts recording your measurements.</p>
                            </div>
                        <?php else: ?>
                            <div class="progress-stats">
                                <?php 
                                $latest_weight = null;
                                $latest_bodyfat = null;
                                foreach($progress_data as $progress) {
                                    if ($progress['measurement_type'] === 'weight' && !$latest_weight) {
                                        $latest_weight = $progress;
                                    }
                                    if ($progress['measurement_type'] === 'body_fat' && !$latest_bodyfat) {
                                        $latest_bodyfat = $progress;
                                    }
                                }
                                ?>
                                <?php if($latest_weight): ?>
                                <div class="progress-card">
                                    <div class="progress-icon">
                                        <i class="fas fa-weight"></i>
                                    </div>
                                    <div class="progress-value"><?= $latest_weight['value'] ?> <?= $latest_weight['unit'] ?></div>
                                    <div class="progress-label">Current Weight</div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if($latest_bodyfat): ?>
                                <div class="progress-card">
                                    <div class="progress-icon">
                                        <i class="fas fa-percentage"></i>
                                    </div>
                                    <div class="progress-value"><?= $latest_bodyfat['value'] ?>%</div>
                                    <div class="progress-label">Body Fat</div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="table-container" style="margin-top: 20px;">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Measurement</th>
                                            <th>Value</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($progress_data as $progress): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($progress['measured_at'])) ?></td>
                                            <td><?= ucfirst(str_replace('_', ' ', $progress['measurement_type'])) ?></td>
                                            <td><?= $progress['value'] ?> <?= $progress['unit'] ?></td>
                                            <td><?= $progress['notes'] ?: '-' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Messages Section -->
                    <div class="admin-section" id="member-messages-section">
                        <div class="section-header">
                            <h2>Messages</h2>
                            <button class="btn btn-confirm" onclick="openModal('newMessageModal')">
                                <i class="fas fa-plus"></i> New Message
                            </button>
                        </div>
                        
                        <div class="messages-container">
                            <div class="conversations-list">
                                <?php if(empty($messages_data)): ?>
                                    <div style="text-align: center; padding: 40px; color: #999;">
                                        <i class="fas fa-envelope-open" style="font-size: 50px; margin-bottom: 15px;"></i>
                                        <p>No messages yet</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach($messages_data as $conversation): ?>
                                    <div class="conversation-item" onclick="loadConversation(<?= $conversation['other_user_id'] ?>)">
                                        <div class="conversation-header">
                                            <div class="conversation-name">
                                                <?= $conversation['first_name'] ?> <?= $conversation['last_name'] ?>
                                                <?php if($conversation['user_type'] === 'trainer'): ?>
                                                    <span style="color: var(--accent); font-size: 12px;">(Trainer)</span>
                                                <?php elseif($conversation['user_type'] === 'admin'): ?>
                                                    <span style="color: var(--gold); font-size: 12px;">(Admin)</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if($conversation['unread_count'] > 0): ?>
                                                <span class="conversation-unread"><?= $conversation['unread_count'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="conversation-preview">
                                            <?= substr($conversation['last_message'] ?? 'No messages', 0, 50) ?>...
                                        </div>
                                        <div class="conversation-time">
                                            <?= $conversation['last_message_time'] ? date('M j, g:i A', strtotime($conversation['last_message_time'])) : '' ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="chat-container">
                                <div class="chat-header">
                                    <h4 id="current-chat-user">Select a conversation</h4>
                                </div>
                                <div class="chat-messages" id="chat-messages">
                                    <div style="text-align: center; padding: 40px; color: #999;">
                                        <i class="fas fa-comments" style="font-size: 50px; margin-bottom: 15px;"></i>
                                        <p>Select a conversation to start messaging</p>
                                    </div>
                                </div>
                                <div class="chat-input" style="display: none;" id="chat-input">
                                    <form class="message-form" id="message-form">
                                        <input type="hidden" id="receiver_id" name="receiver_id">
                                        <input type="text" class="message-input" id="message-input" placeholder="Type your message..." required>
                                        <button type="submit" class="send-message-btn">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ADMIN DASHBOARD SECTION -->
    <?php if($user && $user['role'] === 'admin'): ?>
    <section class="admin-dashboard" id="admin-dashboard">
        <div class="container">
            <div class="admin-layout">
                <!-- Sidebar Navigation -->
                <div class="admin-sidebar">
                    <div class="admin-profile">
                        <h3>Admin Panel</h3>
                        <div class="admin-info">
                            <div class="admin-avatar">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="admin-details">
                                <strong><?= $user['first_name'] ?></strong>
                                <span>Administrator</span>
                            </div>
                        </div>
                    </div>
                    <nav class="admin-sidebar-nav">
                        <button class="sidebar-btn active" onclick="showAdminSection('profile')">
                            <i class="fas fa-user"></i>
                            <span>My Profile</span>
                        </button>
                        <button class="sidebar-btn" onclick="showAdminSection('pending-registrations')">
                            <i class="fas fa-user-clock"></i>
                            <span>Pending Registrations</span>
                        </button>
                        <button class="sidebar-btn" onclick="showAdminSection('announcements-admin')">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
                        </button>
                        <button class="sidebar-btn" onclick="showAdminSection('payments')">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Payment Confirmation</span>
                        </button>
                        <button class="sidebar-btn" onclick="showAdminSection('walkins')">
                            <i class="fas fa-walking"></i>
                            <span>Walk-ins</span>
                        </button>
                        <button class="sidebar-btn" onclick="showAdminSection('membership')">
                            <i class="fas fa-users"></i>
                            <span>Membership</span>
                        </button>
                        <button class="sidebar-btn" onclick="showAdminSection('attendance')">
                            <i class="fas fa-clock"></i>
                            <span>Attendance</span>
                        </button>
                        <button class="sidebar-btn" onclick="showAdminSection('messages')">
                            <i class="fas fa-envelope"></i>
                            <span>Messages</span>
                            <?php if($unreadMessageCount > 0): ?>
                                <span class="message-badge"><?= $unreadMessageCount ?></span>
                            <?php endif; ?>
                        </button>
                    </nav>
                </div>

                <!-- Main Content Area -->
                <div class="admin-content">
                    <!-- Admin Profile Section -->
                    <div class="admin-section active" id="profile-section">
                        <div class="section-header">
                            <h2>Admin Profile</h2>
                        </div>
                        <div class="admin-card">
                            <h3>Welcome, <?= $user['first_name'] ?>!</h3>
                            <p><strong>Username:</strong> <?= $user['username'] ?></p>
                            <p><strong>Role:</strong> Administrator</p>
                            <p><strong>Email:</strong> admin@signusgym.com</p>
                            <p><strong>Last Login:</strong> <?= date('F j, Y g:i A') ?></p>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 30px;">
                            <div class="admin-card" style="text-align: center;">
                                <h4>Pending Registrations</h4>
                                <p style="font-size: 32px; color: var(--warning); margin: 10px 0;"><?= count($pendingRegistrations) ?></p>
                                <small>Waiting for approval</small>
                            </div>
                            <div class="admin-card" style="text-align: center;">
                                <h4>Pending Payments</h4>
                                <p style="font-size: 32px; color: var(--warning); margin: 10px 0;"><?= count($payments) ?></p>
                                <small>Awaiting confirmation</small>
                            </div>
                            <div class="admin-card" style="text-align: center;">
                                <h4>Total Members</h4>
                                <p style="font-size: 32px; color: var(--success); margin: 10px 0;"><?= count($members) + count($walkins) ?></p>
                                <small>Active users</small>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Registrations Section -->
                    <div class="admin-section" id="pending-registrations-section">
                        <div class="section-header">
                            <h2>Pending Registrations</h2>
                            <span class="status-pending"><?= count($pendingRegistrations) ?> Waiting</span>
                        </div>
                        
                        <?php if(empty($pendingRegistrations)): ?>
                            <div class="admin-card" style="text-align: center; padding: 40px;">
                                <i class="fas fa-check-circle" style="font-size: 50px; color: var(--success); margin-bottom: 20px;"></i>
                                <h3>No Pending Registrations</h3>
                                <p>All registration requests have been processed.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($pendingRegistrations as $registration): ?>
                            <div class="admin-card payment-card">
                                <div class="user-info">
                                    <div class="user-name"><?= $registration['first_name'] ?> <?= $registration['last_name'] ?></div>
                                    <div class="user-contact">
                                        <i class="fas fa-envelope"></i> <?= $registration['email'] ?> | 
                                        <i class="fas fa-phone"></i> <?= $registration['contact_number'] ?> |
                                        <i class="fas fa-tag"></i> <?= ucfirst($registration['user_type']) ?>
                                    </div>
                                    <div class="registration-details">
                                        <small style="color: #999;">
                                            <i class="fas fa-calendar"></i> Registered: <?= date('M j, Y g:i A', strtotime($registration['created_at'])) ?>
                                            <?php if($registration['user_type'] === 'gymrat'): ?>
                                                | <i class="fas fa-id-card"></i> Membership: <?= $registration['membership_type'] ?? 'Not specified' ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="activate_user">
                                        <input type="hidden" name="user_id" value="<?= $registration['id'] ?>">
                                        <button type="submit" class="btn btn-confirm">
                                            <i class="fas fa-check"></i> Activate
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="reject_user">
                                        <input type="hidden" name="user_id" value="<?= $registration['id'] ?>">
                                        <button type="submit" class="btn" style="background: var(--danger); color: white;">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Announcements Management Section -->
                    <div class="admin-section" id="announcements-admin-section">
                        <div class="section-header">
                            <h2>Manage Announcements</h2>
                            <button class="btn btn-confirm" onclick="openModal('createAnnouncementModal')">
                                <i class="fas fa-plus"></i> Create Announcement
                            </button>
                        </div>
                        <div class="admin-card">
                            <h3>Current Announcements</h3>
                            <?php foreach($announcements as $announcement): ?>
                            <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 4px solid var(--gold);">
                                <h4 style="color: var(--gold); margin-bottom: 10px;"><?= $announcement['title'] ?></h4>
                                <p style="margin-bottom: 10px; color: #ccc;"><?= strip_tags(substr($announcement['content'], 0, 200)) ?>...</p>
                                <small style="color: #999;">Posted on: <?= date('M j, Y g:i A', strtotime($announcement['created_at'])) ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Payment Confirmation Section -->
                    <div class="admin-section" id="payments-section">
                        <div class="section-header">
                            <h2>Payment Confirmation</h2>
                            <span class="status-pending"><?= count($payments) ?> Pending</span>
                        </div>
                        
                        <div class="setup-instructions" style="margin-bottom: 25px;">
                            <h4>💳 Payment Confirmation Process</h4>
                            <p>When you confirm a payment, you will be prompted to create a username and password for the user.</p>
                        </div>
                        
                        <?php if(empty($payments)): ?>
                            <div class="admin-card" style="text-align: center; padding: 40px;">
                                <i class="fas fa-check-circle" style="font-size: 50px; color: var(--success); margin-bottom: 20px;"></i>
                                <h3>No Pending Payments</h3>
                                <p>All payments have been confirmed.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($payments as $payment): ?>
                            <div class="admin-card payment-card">
                                <div class="user-info">
                                    <div class="user-name"><?= $payment['first_name'] ?> <?= $payment['last_name'] ?></div>
                                    <div class="user-contact">
                                        <i class="fas fa-phone"></i> <?= $payment['contact_number'] ?> 
                                        | <i class="fas fa-tag"></i> <?= ucfirst($payment['membership_type']) ?>
                                    </div>
                                </div>
                                <div class="payment-details">
                                    <div class="payment-amount">₱<?= number_format($payment['amount'], 2) ?></div>
                                    <div class="payment-method">
                                        <i class="fas fa-<?= $payment['payment_method'] === 'gcash' ? 'mobile-alt' : 'money-bill-wave' ?>"></i>
                                        <?= strtoupper($payment['payment_method']) ?>
                                    </div>
                                    <?php if($payment['reference_number']): ?>
                                        <div class="payment-reference" style="font-size: 12px; color: #999;">
                                            <i class="fas fa-receipt"></i> Ref: <?= $payment['reference_number'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <form method="POST" class="action-buttons">
                                    <input type="hidden" name="action" value="confirm_payment">
                                    <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                    <button type="submit" class="btn btn-confirm">
                                        <i class="fas fa-check"></i> Confirm Payment
                                    </button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Walk-ins Section -->
                    <div class="admin-section" id="walkins-section">
                        <div class="section-header">
                            <h2>Walk-in Users</h2>
                            <span class="status-active"><?= count($walkins) ?> Users</span>
                        </div>
                        <div class="table-container">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Start Date</th>
                                        <th>Payment Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($walkins as $walkin): ?>
                                    <tr>
                                        <td>
                                            <strong><?= $walkin['first_name'] ?> <?= $walkin['last_name'] ?></strong>
                                        </td>
                                        <td><?= $walkin['contact_number'] ?></td>
                                        <td><?= date('M j, Y', strtotime($walkin['start_date'])) ?></td>
                                        <td class="status-<?= $walkin['payment_status'] ?>">
                                            <i class="fas fa-<?= $walkin['payment_status'] === 'confirmed' ? 'check-circle' : 'clock' ?>"></i>
                                            <?= ucfirst($walkin['payment_status']) ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="check_in_user">
                                                <input type="hidden" name="user_id" value="<?= $walkin['id'] ?>">
                                                <button type="submit" class="btn btn-checkin">
                                                    <i class="fas fa-sign-in-alt"></i> Check In
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Membership Section -->
                    <div class="admin-section" id="membership-section">
                        <div class="section-header">
                            <h2>Membership Users</h2>
                            <span class="status-active"><?= count($members) ?> Members</span>
                        </div>
                        <div class="table-container">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Start Date</th>
                                        <th>Expiration</th>
                                        <th>Payment Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($members as $member): ?>
                                    <tr>
                                        <td>
                                            <strong><?= $member['first_name'] ?> <?= $member['last_name'] ?></strong>
                                        </td>
                                        <td><?= $member['contact_number'] ?></td>
                                        <td><?= date('M j, Y', strtotime($member['start_date'])) ?></td>
                                        <td>
                                            <?php if($member['expiration_date']): ?>
                                                <?php 
                                                    $expiration = strtotime($member['expiration_date']);
                                                    $today = strtotime('today');
                                                    $class = $expiration < $today ? 'status-pending' : 'status-confirmed';
                                                ?>
                                                <span class="<?= $class ?>">
                                                    <?= date('M j, Y', $expiration) ?>
                                                    <?php if($expiration < $today): ?>
                                                        <br><small>(Expired)</small>
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="status-pending">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="status-<?= $member['payment_status'] ?>">
                                            <i class="fas fa-<?= $member['payment_status'] === 'confirmed' ? 'check-circle' : 'clock' ?>"></i>
                                            <?= ucfirst($member['payment_status']) ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="check_in_user">
                                                <input type="hidden" name="user_id" value="<?= $member['id'] ?>">
                                                <button type="submit" class="btn btn-checkin">
                                                    <i class="fas fa-sign-in-alt"></i> Check In
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Attendance Section -->
                    <div class="admin-section" id="attendance-section">
                        <div class="section-header">
                            <h2>Attendance Records</h2>
                            <span class="status-active"><?= count($attendance) ?> Records</span>
                        </div>
                        <div class="table-container">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($attendance as $record): ?>
                                    <tr>
                                        <td><?= $record['first_name'] ?> <?= $record['last_name'] ?></td>
                                        <td><?= $record['contact_number'] ?></td>
                                        <td><?= date('M j, Y g:i A', strtotime($record['check_in'])) ?></td>
                                        <td>
                                            <?php if($record['check_out']): ?>
                                                <?= date('M j, Y g:i A', strtotime($record['check_out'])) ?>
                                            <?php else: ?>
                                                <span class="status-pending">
                                                    <i class="fas fa-running"></i> Still in gym
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($record['duration_minutes']): ?>
                                                <?= floor($record['duration_minutes'] / 60) ?>h <?= $record['duration_minutes'] % 60 ?>m
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(!$record['check_out']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="check_out_user">
                                                <input type="hidden" name="attendance_id" value="<?= $record['id'] ?>">
                                                <button type="submit" class="btn btn-checkout">
                                                    <i class="fas fa-sign-out-alt"></i> Check Out
                                                </button>
                                            </form>
                                            <?php else: ?>
                                                <span class="status-confirmed">Completed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Messages Section -->
                    <div class="admin-section" id="messages-section">
                        <div class="section-header">
                            <h2>Messages</h2>
                            <button class="btn btn-confirm" onclick="openModal('newMessageModal')">
                                <i class="fas fa-plus"></i> New Message
                            </button>
                        </div>
                        
                        <div class="messages-container">
                            <div class="conversations-list">
                                <?php if(empty($messages_data)): ?>
                                    <div style="text-align: center; padding: 40px; color: #999;">
                                        <i class="fas fa-envelope-open" style="font-size: 50px; margin-bottom: 15px;"></i>
                                        <p>No messages yet</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach($messages_data as $conversation): ?>
                                    <div class="conversation-item" onclick="loadConversation(<?= $conversation['other_user_id'] ?>)">
                                        <div class="conversation-header">
                                            <div class="conversation-name">
                                                <?= $conversation['first_name'] ?> <?= $conversation['last_name'] ?>
                                                <?php if($conversation['user_type'] === 'trainer'): ?>
                                                    <span style="color: var(--accent); font-size: 12px;">(Trainer)</span>
                                                <?php elseif($conversation['user_type'] === 'admin'): ?>
                                                    <span style="color: var(--gold); font-size: 12px;">(Admin)</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if($conversation['unread_count'] > 0): ?>
                                                <span class="conversation-unread"><?= $conversation['unread_count'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="conversation-preview">
                                            <?= substr($conversation['last_message'] ?? 'No messages', 0, 50) ?>...
                                        </div>
                                        <div class="conversation-time">
                                            <?= $conversation['last_message_time'] ? date('M j, g:i A', strtotime($conversation['last_message_time'])) : '' ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="chat-container">
                                <div class="chat-header">
                                    <h4 id="current-chat-user">Select a conversation</h4>
                                </div>
                                <div class="chat-messages" id="chat-messages">
                                    <div style="text-align: center; padding: 40px; color: #999;">
                                        <i class="fas fa-comments" style="font-size: 50px; margin-bottom: 15px;"></i>
                                        <p>Select a conversation to start messaging</p>
                                    </div>
                                </div>
                                <div class="chat-input" style="display: none;" id="chat-input">
                                    <form class="message-form" id="message-form">
                                        <input type="hidden" id="receiver_id" name="receiver_id">
                                        <input type="text" class="message-input" id="message-input" placeholder="Type your message..." required>
                                        <button type="submit" class="send-message-btn">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- TRAINER DASHBOARD SECTION -->
    <?php if($user && $user['role'] === 'trainer'): ?>
    <section class="trainer-dashboard" id="trainer-dashboard">
        <div class="container">
            <div class="trainer-layout">
                <!-- Sidebar Navigation -->
                <div class="trainer-sidebar">
                    <div class="trainer-profile">
                        <h3>Trainer Panel</h3>
                        <div class="trainer-info">
                            <div class="trainer-avatar">
                                <i class="fas fa-dumbbell"></i>
                            </div>
                            <div class="trainer-details">
                                <strong><?= $user['first_name'] ?></strong>
                                <span>Certified Trainer</span>
                            </div>
                        </div>
                    </div>
                    <nav class="trainer-sidebar-nav">
                        <button class="trainer-sidebar-btn active" onclick="showTrainerSection('profile')">
                            <i class="fas fa-user"></i>
                            <span>My Profile</span>
                        </button>
                        <button class="trainer-sidebar-btn" onclick="showTrainerSection('clients')">
                            <i class="fas fa-users"></i>
                            <span>My Clients</span>
                        </button>
                        <button class="trainer-sidebar-btn" onclick="showTrainerSection('appointments')">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Appointments</span>
                        </button>
                        <button class="trainer-sidebar-btn" onclick="showTrainerSection('workouts')">
                            <i class="fas fa-dumbbell"></i>
                            <span>Workout Plans</span>
                        </button>
                        <button class="trainer-sidebar-btn" onclick="showTrainerSection('progress')">
                            <i class="fas fa-chart-line"></i>
                            <span>Client Progress</span>
                        </button>
                        <button class="trainer-sidebar-btn" onclick="showTrainerSection('messages')">
                            <i class="fas fa-envelope"></i>
                            <span>Messages</span>
                            <?php if($unreadMessageCount > 0): ?>
                                <span class="message-badge"><?= $unreadMessageCount ?></span>
                            <?php endif; ?>
                        </button>
                    </nav>
                </div>

                <!-- Main Content Area -->
                <div class="trainer-content">
                    <!-- Trainer Profile Section -->
                    <div class="trainer-section active" id="trainer-profile-section">
                        <div class="section-header">
                            <h2>Trainer Profile</h2>
                        </div>
                        <div class="admin-card">
                            <h3>Welcome, <?= $user['first_name'] ?>!</h3>
                            <p><strong>Username:</strong> <?= $user['username'] ?></p>
                            <p><strong>Role:</strong> Certified Trainer</p>
                            <p><strong>Specialization:</strong> Strength Training & Conditioning</p>
                            <p><strong>Experience:</strong> 5+ Years</p>
                        </div>
                        
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-number"><?= count($trainer_data['clients'] ?? []) ?></div>
                                <div class="stat-label">Active Clients</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stat-number"><?= count($trainer_data['appointments'] ?? []) ?></div>
                                <div class="stat-label">Appointments</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-dumbbell"></i>
                                </div>
                                <div class="stat-number"><?= count($trainer_data['workout_plans'] ?? []) ?></div>
                                <div class="stat-label">Workout Plans</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="stat-number"><?= count($trainer_data['client_progress'] ?? []) ?></div>
                                <div class="stat-label">Progress Records</div>
                            </div>
                        </div>
                    </div>

                    <!-- Clients Section -->
                    <div class="trainer-section" id="trainer-clients-section">
                        <div class="section-header">
                            <h2>My Clients</h2>
                            <span class="status-active"><?= count($trainer_data['clients'] ?? []) ?> Clients</span>
                        </div>
                        
                        <?php if(empty($trainer_data['clients'])): ?>
                            <div class="admin-card" style="text-align: center; padding: 40px;">
                                <i class="fas fa-users" style="font-size: 50px; color: var(--accent); margin-bottom: 20px;"></i>
                                <h3>No Clients Assigned</h3>
                                <p>You don't have any clients assigned yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($trainer_data['clients'] as $client): ?>
                            <div class="client-card">
                                <div class="client-header">
                                    <div class="client-name"><?= $client['first_name'] ?> <?= $client['last_name'] ?></div>
                                    <div class="status-badge status-active">Active</div>
                                </div>
                                <div class="client-contact">
                                    <i class="fas fa-phone"></i> <?= $client['contact_number'] ?> | 
                                    <i class="fas fa-tag"></i> <?= ucfirst($client['membership_type']) ?>
                                </div>
                                <div class="action-buttons">
                                    <button class="btn btn-confirm" onclick="openModal('createWorkoutModal', <?= $client['id'] ?>)">
                                        <i class="fas fa-dumbbell"></i> Create Workout
                                    </button>
                                    <button class="btn btn-checkin" onclick="openModal('trackProgressModal', <?= $client['id'] ?>)">
                                        <i class="fas fa-chart-line"></i> Track Progress
                                    </button>
                                    <button class="btn" onclick="openModal('sendMessageModal', <?= $client['id'] ?>)" style="background: var(--accent); color: white;">
                                        <i class="fas fa-envelope"></i> Message
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Appointments Section -->
                    <div class="trainer-section" id="trainer-appointments-section">
                        <div class="section-header">
                            <h2>Appointments</h2>
                            <span class="status-active"><?= count($trainer_data['appointments'] ?? []) ?> Scheduled</span>
                        </div>
                        
                        <?php if(empty($trainer_data['appointments'])): ?>
                            <div class="admin-card" style="text-align: center; padding: 40px;">
                                <i class="fas fa-calendar-times" style="font-size: 50px; color: var(--accent); margin-bottom: 20px;"></i>
                                <h3>No Appointments</h3>
                                <p>You don't have any scheduled appointments.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($trainer_data['appointments'] as $appointment): ?>
                            <div class="appointment-card">
                                <div class="appointment-time">
                                    <i class="fas fa-clock"></i> 
                                    <?= date('M j, Y g:i A', strtotime($appointment['appointment_date'])) ?>
                                </div>
                                <div class="appointment-client">
                                    <i class="fas fa-user"></i> 
                                    <?= $appointment['first_name'] ?> <?= $appointment['last_name'] ?>
                                </div>
                                <div class="appointment-service">
                                    <i class="fas fa-dumbbell"></i> 
                                    <?= ucfirst($appointment['service_type']) ?> Training
                                    <span class="status-badge status-<?= $appointment['status'] ?>" style="float: right;">
                                        <?= ucfirst($appointment['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Workout Plans Section -->
                    <div class="trainer-section" id="trainer-workouts-section">
                        <div class="section-header">
                            <h2>Workout Plans</h2>
                            <button class="btn btn-confirm" onclick="openModal('createWorkoutModal')">
                                <i class="fas fa-plus"></i> Create Plan
                            </button>
                        </div>
                        
                        <?php if(empty($trainer_data['workout_plans'])): ?>
                            <div class="admin-card" style="text-align: center; padding: 40px;">
                                <i class="fas fa-dumbbell" style="font-size: 50px; color: var(--accent); margin-bottom: 20px;"></i>
                                <h3>No Workout Plans</h3>
                                <p>You haven't created any workout plans yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($trainer_data['workout_plans'] as $plan): ?>
                            <div class="admin-card">
                                <h4 style="color: var(--accent); margin-bottom: 10px;"><?= $plan['plan_name'] ?></h4>
                                <p><strong>Client:</strong> <?= $plan['first_name'] ?> <?= $plan['last_name'] ?></p>
                                <p><strong>Difficulty:</strong> <?= ucfirst($plan['difficulty']) ?></p>
                                <p><strong>Description:</strong> <?= $plan['description'] ?></p>
                                <p><strong>Created:</strong> <?= date('M j, Y', strtotime($plan['created_at'])) ?></p>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Client Progress Section -->
                    <div class="trainer-section" id="trainer-progress-section">
                        <div class="section-header">
                            <h2>Client Progress</h2>
                            <span class="status-active"><?= count($trainer_data['client_progress'] ?? []) ?> Records</span>
                        </div>
                        
                        <?php if(empty($trainer_data['client_progress'])): ?>
                            <div class="admin-card" style="text-align: center; padding: 40px;">
                                <i class="fas fa-chart-line" style="font-size: 50px; color: var(--accent); margin-bottom: 20px;"></i>
                                <h3>No Progress Records</h3>
                                <p>No client progress has been recorded yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Measurement</th>
                                            <th>Value</th>
                                            <th>Date</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($trainer_data['client_progress'] as $progress): ?>
                                        <tr>
                                            <td><?= $progress['first_name'] ?> <?= $progress['last_name'] ?></td>
                                            <td><?= ucfirst(str_replace('_', ' ', $progress['measurement_type'])) ?></td>
                                            <td><?= $progress['value'] ?> <?= $progress['unit'] ?></td>
                                            <td><?= date('M j, Y', strtotime($progress['measured_at'])) ?></td>
                                            <td><?= $progress['notes'] ?: '-' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Messages Section -->
                    <div class="trainer-section" id="trainer-messages-section">
                        <div class="section-header">
                            <h2>Messages</h2>
                            <button class="btn btn-confirm" onclick="openModal('newMessageModal')">
                                <i class="fas fa-plus"></i> New Message
                            </button>
                        </div>
                        
                        <div class="messages-container">
                            <div class="conversations-list">
                                <?php if(empty($messages_data)): ?>
                                    <div style="text-align: center; padding: 40px; color: #999;">
                                        <i class="fas fa-envelope-open" style="font-size: 50px; margin-bottom: 15px;"></i>
                                        <p>No messages yet</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach($messages_data as $conversation): ?>
                                    <div class="conversation-item" onclick="loadConversation(<?= $conversation['other_user_id'] ?>)">
                                        <div class="conversation-header">
                                            <div class="conversation-name">
                                                <?= $conversation['first_name'] ?> <?= $conversation['last_name'] ?>
                                                <?php if($conversation['user_type'] === 'trainer'): ?>
                                                    <span style="color: var(--accent); font-size: 12px;">(Trainer)</span>
                                                <?php elseif($conversation['user_type'] === 'admin'): ?>
                                                    <span style="color: var(--gold); font-size: 12px;">(Admin)</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if($conversation['unread_count'] > 0): ?>
                                                <span class="conversation-unread"><?= $conversation['unread_count'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="conversation-preview">
                                            <?= substr($conversation['last_message'] ?? 'No messages', 0, 50) ?>...
                                        </div>
                                        <div class="conversation-time">
                                            <?= $conversation['last_message_time'] ? date('M j, g:i A', strtotime($conversation['last_message_time'])) : '' ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="chat-container">
                                <div class="chat-header">
                                    <h4 id="current-chat-user">Select a conversation</h4>
                                </div>
                                <div class="chat-messages" id="chat-messages">
                                    <div style="text-align: center; padding: 40px; color: #999;">
                                        <i class="fas fa-comments" style="font-size: 50px; margin-bottom: 15px;"></i>
                                        <p>Select a conversation to start messaging</p>
                                    </div>
                                </div>
                                <div class="chat-input" style="display: none;" id="chat-input">
                                    <form class="message-form" id="message-form">
                                        <input type="hidden" id="receiver_id" name="receiver_id">
                                        <input type="text" class="message-input" id="message-input" placeholder="Type your message..." required>
                                        <button type="submit" class="send-message-btn">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content">
                <h1>Transform Your Body, <span>Transform Your Life</span></h1>
                <p>Signus Gym Monitoring System helps you track your fitness journey with advanced tools for members, trainers, and administrators. Join our community today!</p>
                <button class="cta-button" onclick="openModal('registerModal')">Start Your Journey</button>
            </div>
        </div>
    </section>

    <!-- Announcements Section -->
    <section class="announcements" id="announcements">
        <div class="container">
            <div class="section-title">
                <h2>Announcements</h2>
                <?php if($user && $user['role'] === 'admin'): ?>
                    <button class="cta-button" onclick="openModal('createAnnouncementModal')" style="margin-top: 20px;">Create Announcement</button>
                <?php endif; ?>
            </div>
            
            <?php foreach($announcements as $announcement): ?>
            <div class="announcement-card">
                <div class="announcement-header">
                    <h3 class="announcement-title"><?= $announcement['title'] ?></h3>
                    <span class="announcement-date"><?= date('F j, Y', strtotime($announcement['created_at'])) ?></span>
                </div>
                <div class="announcement-content">
                    <?= $announcement['content'] ?>
                </div>
                <div class="comments-section">
                    <?php foreach($announcement['comments'] as $comment): ?>
                    <div class="comment">
                        <div class="comment-header">
                            <span class="comment-author">
                                <?= $comment['first_name'] ?> <?= $comment['last_name'] ?>
                                <?php if($comment['user_type'] === 'admin'): ?>
                                    <span style="color: var(--gold); font-size: 12px;">(Admin)</span>
                                <?php elseif($comment['user_type'] === 'trainer'): ?>
                                    <span style="color: var(--accent); font-size: 12px;">(Trainer)</span>
                                <?php endif; ?>
                            </span>
                            <span class="comment-date"><?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?></span>
                        </div>
                        <p><?= $comment['comment_text'] ?></p>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if($user): ?>
                    <form method="POST" class="comment-form">
                        <input type="hidden" name="action" value="add_comment">
                        <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                        <input type="text" name="comment_text" class="comment-input" placeholder="Add a comment..." required>
                        <button type="submit" class="comment-btn">Post</button>
                    </form>
                    <?php else: ?>
                    <p style="color: #999; text-align: center;">Please login to comment</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Membership Perks Section -->
    <section class="perks" id="perks">
        <div class="container">
            <div class="section-title">
                <h2>Membership Perks</h2>
            </div>
            <div class="perks-grid">
                <div class="perk-card">
                    <div class="perk-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <h3>Personalized Training Program</h3>
                    <p>Your custom workout blueprint designed specifically for your goals and fitness level.</p>
                </div>
                <div class="perk-card">
                    <div class="perk-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Progress Tracking</h3>
                    <p>Regular assessments and measurements to track your transformation journey.</p>
                </div>
                <div class="perk-card">
                    <div class="perk-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>24/7 Gym Access</h3>
                    <p>Work out on your own schedule with round-the-clock access to our facilities.</p>
                </div>
                <div class="perk-card">
                    <div class="perk-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h3>Premium Facilities</h3>
                    <p>Clean, secure locker rooms and shower facilities for your convenience.</p>
                </div>
                <div class="perk-card">
                    <div class="perk-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h3>Goal-Setting Consultations</h3>
                    <p>Monthly sessions with trainers to set and review your fitness goals.</p>
                </div>
                <div class="perk-card">
                    <div class="perk-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Community Events</h3>
                    <p>Join member challenges and social events to stay motivated with our fitness community.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title">
                <h2>System Features</h2>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3>Admin Dashboard</h3>
                    <p>Monitor gym users, track attendance, manage memberships, view financial records, and confirm payments with our comprehensive admin dashboard.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <h3>Gym User Portal</h3>
                    <p>Access workout plans, track progress, schedule sessions with trainers, view announcements, and manage your membership details.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3>Trainer Dashboard</h3>
                    <p>Manage your clients, view scheduled appointments, track client progress, update training programs, and interact with announcements.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Registration Section -->
    <section class="registration" id="registration">
        <div class="container">
            <div class="section-title">
                <h2>Registration Options</h2>
            </div>
            <div class="registration-options">
                <div class="option-card">
                    <div class="option-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3>Gymrat</h3>
                    <p>For gym enthusiasts who want to track their progress and access premium features. Complete registration and payment to get started.</p>
                    <button class="option-button" onclick="openRegistrationModal('gymrat')">Register as Gymrat</button>
                </div>
                <div class="option-card">
                    <div class="option-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3>Admin</h3>
                    <p>For gym administrators to manage the facility, members, and financial aspects. Use generic credentials for immediate access.</p>
                    <button class="option-button" onclick="openRegistrationModal('admin')">Register as Admin</button>
                </div>
                <div class="option-card">
                    <div class="option-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3>Trainer</h3>
                    <p>For fitness professionals to manage clients, schedules, and training programs. Use generic credentials for immediate access.</p>
                    <button class="option-button" onclick="openRegistrationModal('trainer')">Register as Trainer</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Payment Section -->
    <section class="payment-info" id="payment">
        <div class="container">
            <h2>Payment Methods</h2>
            <p>Complete your registration by making a payment through any of these methods. Your account will be activated once payment is confirmed.</p>
            <div class="payment-methods">
                <div class="payment-method">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>GCash</h3>
                    <p>09652432510</p>
                </div>
                <div class="payment-method">
                    <i class="fas fa-money-bill-wave"></i>
                    <h3>Direct Payment</h3>
                    <p>At Gym Front Desk</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>About Signus</h3>
                    <p>Signus is a comprehensive gym monitoring system designed to streamline operations for gym owners, trainers, and members.</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <a href="#home">Home</a>
                    <a href="#announcements">Announcements</a>
                    <a href="#perks">Membership Perks</a>
                    <a href="#registration">Registration</a>
                    <a href="#payment">Payment</a>
                </div>
                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <p><i class="fas fa-map-marker-alt"></i> 123 Fitness Street, Gym City</p>
                    <p><i class="fas fa-phone"></i> +1 234 567 8900</p>
                    <p><i class="fas fa-envelope"></i> info@signusgym.com</p>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2023 Signus Gym Monitoring System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- NEW MODALS FOR MESSAGING AND COACHING -->

    <!-- Book Coaching Modal -->
    <div class="modal" id="bookCoachingModal">
        <div class="modal-content" style="max-width: 600px;">
            <span class="close-modal" onclick="closeModal('bookCoachingModal')">&times;</span>
            <h2>Book Coaching Session</h2>
            <form method="POST">
                <input type="hidden" name="action" value="book_coaching">
                
                <div class="form-group">
                    <label>Session Type</label>
                    <div class="coaching-types">
                        <div class="coaching-type-card" onclick="selectCoachingType('personal')">
                            <div class="coaching-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <h4>Personal Training</h4>
                            <p>One-on-one session with a trainer</p>
                            <input type="radio" name="service_type" value="personal" style="display: none;">
                        </div>
                        <div class="coaching-type-card" onclick="selectCoachingType('online')">
                            <div class="coaching-icon">
                                <i class="fas fa-laptop"></i>
                            </div>
                            <h4>Online Coaching</h4>
                            <p>Virtual training session</p>
                            <input type="radio" name="service_type" value="online" style="display: none;">
                        </div>
                        <div class="coaching-type-card" onclick="selectCoachingType('hybrid')">
                            <div class="coaching-icon">
                                <i class="fas fa-blender-phone"></i>
                            </div>
                            <h4>Hybrid Coaching</h4>
                            <p>Combination of in-person and online</p>
                            <input type="radio" name="service_type" value="hybrid" style="display: none;">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="trainerSelect">Select Trainer</label>
                    <select id="trainerSelect" name="trainer_id" required>
                        <option value="">Choose a trainer</option>
                        <?php foreach($trainers as $trainer): ?>
                            <option value="<?= $trainer['id'] ?>">
                                <?= $trainer['first_name'] ?> <?= $trainer['last_name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="appointmentDate">Date</label>
                    <input type="date" id="appointmentDate" name="appointment_date" required min="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-group">
                    <label for="appointmentTime">Time</label>
                    <input type="time" id="appointmentTime" name="appointment_time" required>
                </div>
                
                <div class="form-group">
                    <label for="sessionNotes">Notes (Optional)</label>
                    <textarea id="sessionNotes" name="notes" placeholder="Any specific goals or requirements..."></textarea>
                </div>
                
                <button type="submit" class="submit-btn">Book Session</button>
            </form>
        </div>
    </div>

    <!-- New Message Modal -->
    <div class="modal" id="newMessageModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('newMessageModal')">&times;</span>
            <h2>New Message</h2>
            <form method="POST">
                <input type="hidden" name="action" value="send_new_message">
                
                <div class="form-group">
                    <label for="messageReceiver">To</label>
                    <select id="messageReceiver" name="receiver_id" required>
                        <option value="">Select recipient</option>
                        <optgroup label="Trainers">
                            <?php 
                            $trainers = [];
                            if ($pdo) {
                                $stmt = $pdo->query("SELECT * FROM users WHERE user_type = 'trainer' AND status = 'active'");
                                $trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            }
                            foreach($trainers as $trainer): ?>
                                <option value="<?= $trainer['id'] ?>">
                                    <?= $trainer['first_name'] ?> <?= $trainer['last_name'] ?> (Trainer)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Admin">
                            <?php 
                            $admins = [];
                            if ($pdo) {
                                $stmt = $pdo->query("SELECT * FROM users WHERE user_type = 'admin' AND status = 'active'");
                                $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            }
                            foreach($admins as $admin): ?>
                                <option value="<?= $admin['id'] ?>">
                                    <?= $admin['first_name'] ?> <?= $admin['last_name'] ?> (Admin)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="messageSubject">Subject (Optional)</label>
                    <input type="text" id="messageSubject" name="subject" placeholder="Message subject...">
                </div>
                
                <div class="form-group">
                    <label for="newMessageContent">Message</label>
                    <textarea id="newMessageContent" name="message" required placeholder="Type your message here..."></textarea>
                </div>
                
                <button type="submit" class="submit-btn">Send Message</button>
            </form>
        </div>
    </div>

    <!-- Upgrade Membership Modal -->
    <div class="modal" id="upgradeModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('upgradeModal')">&times;</span>
            <h2>Upgrade to Membership</h2>
            <div class="admin-card">
                <h3 style="color: var(--gold); text-align: center;">Membership Benefits</h3>
                <ul style="margin: 20px 0; padding-left: 20px;">
                    <li>📧 Full messaging system with trainers and admin</li>
                    <li>💪 Book all 3 types of coaching sessions</li>
                    <li>📊 Complete progress tracking</li>
                    <li>📅 Advanced session management</li>
                    <li>👥 Direct communication with assigned trainers</li>
                </ul>
                <div style="text-align: center; margin: 25px 0;">
                    <h2 style="color: var(--success);">₱500 / month</h2>
                    <p style="color: #999;">One-time payment, 30-day access</p>
                </div>
            </div>
            <div style="text-align: center;">
                <p>Contact admin to upgrade your membership:</p>
                <p><strong>Email:</strong> admin@signusgym.com</p>
                <p><strong>Phone:</strong> 09123456789</p>
            </div>
        </div>
    </div>

    <!-- EXISTING MODALS (all your original modals remain unchanged) -->

    <!-- Login Modal -->
    <div class="modal" id="loginModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('loginModal')">&times;</span>
            <h2>Login to Your Account</h2>
            
            <!-- NEW: Setup Token Notice -->
            <?php if(isset($_GET['setup_token'])): ?>
            <div class="setup-instructions" style="margin-bottom: 20px;">
                <h4>🎉 Account Setup Ready!</h4>
                <p>Your payment has been confirmed. Please create your username and password to activate your account.</p>
                <p><strong>Setup Token:</strong> <?= htmlspecialchars($_GET['setup_token']) ?></p>
                <p><a href="?setup_token=<?= htmlspecialchars($_GET['setup_token']) ?>" style="color: var(--gold); font-weight: bold;">Click here to setup your account</a></p>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="loginUsername">Username</label>
                    <input type="text" id="loginUsername" name="username" required>
                </div>
                <div class="form-group">
                    <label for="loginPassword">Password</label>
                    <input type="password" id="loginPassword" name="password" required>
                </div>
                <button type="submit" class="submit-btn">Login</button>
            </form>
            <p style="text-align: center; margin-top: 20px;">
                <a href="#" onclick="openModal('forgotPasswordModal'); closeModal('loginModal')" style="color: var(--gold);">Forgot password?</a>
            </p>
            <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; margin-top: 20px;">
                <p style="text-align: center; color: #999; font-size: 14px;">
                    <strong>Demo Credentials:</strong><br>
                    Admin: admin / admin123<br>
                    Trainers: trainer1 / trainer123
                </p>
            </div>
            
            <!-- NEW: Setup Information -->
            <div style="background: rgba(241, 196, 15, 0.1); padding: 15px; border-radius: 8px; margin-top: 20px; border-left: 4px solid var(--gold);">
                <p style="text-align: center; color: var(--gold); font-size: 14px;">
                    <strong>New User?</strong> After payment confirmation, you'll receive a setup token to create your account.
                </p>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal" id="forgotPasswordModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('forgotPasswordModal')">&times;</span>
            <h2>Reset Password</h2>
            <form method="POST">
                <input type="hidden" name="action" value="forgot_password">
                <div class="form-group">
                    <label for="forgotEmail">Email Address</label>
                    <input type="email" id="forgotEmail" name="email" required>
                </div>
                <button type="submit" class="submit-btn">Send Reset Link</button>
            </form>
        </div>
    </div>

    <!-- Registration Modal -->
    <div class="modal" id="registerModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('registerModal')">&times;</span>
            <h2 id="registerTitle">Register</h2>
            <form method="POST" id="registerForm">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="user_type" id="userType" value="gymrat">
                
                <div class="form-group">
                    <label for="firstName">First Name</label>
                    <input type="text" id="firstName" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="lastName">Last Name</label>
                    <input type="text" id="lastName" name="last_name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="contact">Contact Number</label>
                    <input type="text" id="contact" name="contact_number" required>
                </div>
                
                <!-- Membership Type (only for gymrat) -->
                <div class="form-group" id="membershipTypeGroup">
                    <label for="membershipType">Membership Type</label>
                    <select id="membershipType" name="membership_type" required>
                        <option value="">Select Membership</option>
                        <option value="walk-in">Walk-in (₱60)</option>
                        <option value="membership">Membership (₱500)</option>
                    </select>
                </div>
                
                <button type="submit" class="submit-btn">Complete Registration</button>
            </form>
        </div>
    </div>

    <!-- Create Announcement Modal -->
    <div class="modal" id="createAnnouncementModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('createAnnouncementModal')">&times;</span>
            <h2>Create Announcement</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_announcement">
                <div class="form-group">
                    <label for="announcementTitle">Title</label>
                    <input type="text" id="announcementTitle" name="title" required>
                </div>
                <div class="form-group">
                    <label for="announcementContent">Content</label>
                    <textarea id="announcementContent" name="content" required></textarea>
                </div>
                <button type="submit" class="submit-btn">Create Announcement</button>
            </form>
        </div>
    </div>

    <!-- Create Workout Plan Modal -->
    <div class="modal" id="createWorkoutModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('createWorkoutModal')">&times;</span>
            <h2>Create Workout Plan</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_workout_plan">
                <input type="hidden" name="client_id" id="workoutClientId">
                
                <div class="form-group">
                    <label for="planName">Plan Name</label>
                    <input type="text" id="planName" name="plan_name" required>
                </div>
                <div class="form-group">
                    <label for="planDescription">Description</label>
                    <textarea id="planDescription" name="description" required></textarea>
                </div>
                <div class="form-group">
                    <label for="difficulty">Difficulty Level</label>
                    <select id="difficulty" name="difficulty" required>
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>
                
                <div id="exercisesContainer">
                    <h4>Exercises</h4>
                    <div class="exercise-entry">
                        <div class="form-group">
                            <label>Exercise Name</label>
                            <input type="text" name="exercises[0][name]" required>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px;">
                            <div class="form-group">
                                <label>Sets</label>
                                <input type="number" name="exercises[0][sets]" required>
                            </div>
                            <div class="form-group">
                                <label>Reps</label>
                                <input type="text" name="exercises[0][reps]" required>
                            </div>
                            <div class="form-group">
                                <label>Weight</label>
                                <input type="text" name="exercises[0][weight]">
                            </div>
                            <div class="form-group">
                                <label>Day</label>
                                <select name="exercises[0][day]">
                                    <option value="monday">Monday</option>
                                    <option value="tuesday">Tuesday</option>
                                    <option value="wednesday">Wednesday</option>
                                    <option value="thursday">Thursday</option>
                                    <option value="friday">Friday</option>
                                    <option value="saturday">Saturday</option>
                                    <option value="sunday">Sunday</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="button" class="btn" onclick="addExercise()" style="background: var(--accent); color: white; margin-bottom: 15px;">
                    <i class="fas fa-plus"></i> Add Exercise
                </button>
                
                <button type="submit" class="submit-btn">Create Workout Plan</button>
            </form>
        </div>
    </div>

    <!-- Track Progress Modal -->
    <div class="modal" id="trackProgressModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('trackProgressModal')">&times;</span>
            <h2>Track Client Progress</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_client_progress">
                <input type="hidden" name="client_id" id="progressClientId">
                
                <div class="form-group">
                    <label for="measurementType">Measurement Type</label>
                    <select id="measurementType" name="measurement_type" required>
                        <option value="weight">Weight</option>
                        <option value="body_fat">Body Fat %</option>
                        <option value="muscle_mass">Muscle Mass</option>
                        <option value="chest">Chest</option>
                        <option value="waist">Waist</option>
                        <option value="arms">Arms</option>
                        <option value="thighs">Thighs</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="measurementValue">Value</label>
                    <input type="number" id="measurementValue" name="value" step="0.1" required>
                </div>
                <div class="form-group">
                    <label for="progressNotes">Notes</label>
                    <textarea id="progressNotes" name="notes" placeholder="Any additional notes..."></textarea>
                </div>
                
                <button type="submit" class="submit-btn">Save Progress</button>
            </form>
        </div>
    </div>

    <!-- Send Message Modal -->
    <div class="modal" id="sendMessageModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('sendMessageModal')">&times;</span>
            <h2>Send Message</h2>
            <form method="POST">
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="client_id" id="messageClientId">
                
                <div class="form-group">
                    <label for="messageContent">Message</label>
                    <textarea id="messageContent" name="message" required placeholder="Type your message here..."></textarea>
                </div>
                
                <button type="submit" class="submit-btn">Send Message</button>
            </form>
        </div>
    </div>

    <!-- Availability Modal -->
    <div class="modal" id="availabilityModal">
        <div class="modal-content" style="max-width: 600px;">
            <span class="close-modal" onclick="closeModal('availabilityModal')">&times;</span>
            <h2>Set Availability</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_availability">
                
                <?php 
                $days = [
                    'monday' => 'Monday',
                    'tuesday' => 'Tuesday',
                    'wednesday' => 'Wednesday', 
                    'thursday' => 'Thursday',
                    'friday' => 'Friday',
                    'saturday' => 'Saturday',
                    'sunday' => 'Sunday'
                ];
                
                foreach($days as $day_key => $day_name): 
                    $availability = null;
                    foreach($trainer_data['availability'] ?? [] as $avail) {
                        if ($avail['day_of_week'] === $day_key) {
                            $availability = $avail;
                            break;
                        }
                    }
                ?>
                <div class="admin-card" style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4><?= $day_name ?></h4>
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="is_available_<?= $day_key ?>" <?= $availability && $availability['is_available'] ? 'checked' : '' ?>>
                            Available
                        </label>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time_<?= $day_key ?>" value="<?= $availability ? $availability['start_time'] : '09:00' ?>">
                        </div>
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="end_time_<?= $day_key ?>" value="<?= $availability ? $availability['end_time'] : '17:00' ?>">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <button type="submit" class="submit-btn">Update Availability</button>
            </form>
        </div>
    </div>

    <script>
        // NEW: Messaging and Coaching JavaScript
        function showMemberSection(section) {
            // Hide all sections
            document.querySelectorAll('.admin-section').forEach(sec => {
                sec.style.display = 'none';
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.sidebar-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById('member-' + section + '-section').style.display = 'block';
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

        function selectCoachingType(type) {
            // Remove selected class from all cards
            document.querySelectorAll('.coaching-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            event.target.closest('.coaching-type-card').classList.add('selected');
            
            // Set the radio button value
            const radio = event.target.closest('.coaching-type-card').querySelector('input[type="radio"]');
            radio.checked = true;
        }

        function loadConversation(userId) {
            // Show loading state
            document.getElementById('chat-messages').innerHTML = '<div style="text-align: center; padding: 20px;">Loading messages...</div>';
            document.getElementById('chat-input').style.display = 'block';
            
            // In a real implementation, you would fetch messages via AJAX
            // For now, we'll simulate loading
            setTimeout(() => {
                // This would be replaced with actual message loading
                document.getElementById('chat-messages').innerHTML = '<div style="text-align: center; padding: 40px; color: #999;"><p>Conversation loaded</p></div>';
                document.getElementById('receiver_id').value = userId;
            }, 500);
        }

        // Handle message form submission
        document.getElementById('message-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = document.getElementById('message-input').value;
            const receiverId = document.getElementById('receiver_id').value;
            
            if (message && receiverId) {
                // In a real implementation, send via AJAX
                const formData = new FormData();
                formData.append('action', 'send_new_message');
                formData.append('receiver_id', receiverId);
                formData.append('message', message);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    if (response.ok) {
                        document.getElementById('message-input').value = '';
                        // Reload messages
                        loadConversation(receiverId);
                    }
                });
            }
        });

        // Initialize member dashboard
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($user && $user['role'] === 'gymrat'): ?>
            showMemberSection('profile');
            <?php endif; ?>
        });

        // REST OF YOUR EXISTING JAVASCRIPT FUNCTIONS
        // Auto-show admin setup popup if needed
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($showSetupPopup): ?>
            document.getElementById('adminSetupPopup').classList.add('active');
            <?php endif; ?>
            
            <?php if($showSetupForm): ?>
            document.getElementById('accountSetupModal').classList.add('active');
            <?php endif; ?>
        });

        // Close admin setup popup
        function closeAdminSetupPopup() {
            document.getElementById('adminSetupPopup').classList.remove('active');
            // Clear the session data
            fetch('?clear_setup_session=1', {method: 'GET'});
        }

        // Close user setup modal
        function closeSetupModal() {
            document.getElementById('accountSetupModal').classList.remove('active');
            // Remove setup token from URL
            if (window.history.replaceState && window.location.search.includes('setup_token')) {
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('setup-popup-modal')) {
                closeAdminSetupPopup();
            }
            if (event.target.classList.contains('setup-modal')) {
                closeSetupModal();
            }
        });

        // Admin Dashboard Navigation
        function showAdminSection(section) {
            // Hide all sections
            document.querySelectorAll('.admin-section').forEach(sec => {
                sec.style.display = 'none';
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.sidebar-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(section + '-section').style.display = 'block';
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

        // Trainer Dashboard Navigation
        function showTrainerSection(section) {
            // Hide all sections
            document.querySelectorAll('.trainer-section').forEach(sec => {
                sec.style.display = 'none';
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.trainer-sidebar-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById('trainer-' + section + '-section').style.display = 'block';
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

        // Initialize dashboards
        document.addEventListener('DOMContentLoaded', function() {
            // Show profile section by default for admin
            <?php if($user && $user['role'] === 'admin'): ?>
            showAdminSection('profile');
            <?php endif; ?>
            
            // Show profile section by default for trainer
            <?php if($user && $user['role'] === 'trainer'): ?>
            showTrainerSection('profile');
            <?php endif; ?>

            // Animation on scroll
            const featureCards = document.querySelectorAll('.feature-card');
            const optionCards = document.querySelectorAll('.option-card');
            const perkCards = document.querySelectorAll('.perk-card');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = `fadeUp 0.8s forwards ${entry.target.dataset.delay || '0s'}`;
                    }
                });
            }, { threshold: 0.1 });
            
            featureCards.forEach((card, index) => {
                card.dataset.delay = `${index * 0.2}s`;
                observer.observe(card);
            });
            
            optionCards.forEach((card, index) => {
                card.dataset.delay = `${index * 0.2}s`;
                observer.observe(card);
            });
            
            perkCards.forEach((card, index) => {
                card.dataset.delay = `${index * 0.2}s`;
                observer.observe(card);
            });
            
            // Smooth scrolling for navigation links
            document.querySelectorAll('nav a').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    if (this.getAttribute('href').startsWith('#')) {
                        e.preventDefault();
                        const targetId = this.getAttribute('href');
                        document.querySelector(targetId).scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                });
            });
        });
        
        // Modal functions
        function openModal(modalId, clientId = null) {
            if (clientId) {
                // Set client ID for specific modals
                if (modalId === 'createWorkoutModal') {
                    document.getElementById('workoutClientId').value = clientId;
                } else if (modalId === 'trackProgressModal') {
                    document.getElementById('progressClientId').value = clientId;
                } else if (modalId === 'sendMessageModal') {
                    document.getElementById('messageClientId').value = clientId;
                }
            }
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function openRegistrationModal(userType) {
            const modal = document.getElementById('registerModal');
            const title = document.getElementById('registerTitle');
            const userTypeField = document.getElementById('userType');
            const membershipGroup = document.getElementById('membershipTypeGroup');
            
            userTypeField.value = userType;
            title.textContent = `Register as ${userType.charAt(0).toUpperCase() + userType.slice(1)}`;
            
            // Show membership type only for gymrat users
            if (userType === 'gymrat') {
                membershipGroup.style.display = 'block';
                document.getElementById('membershipType').setAttribute('required', 'required');
            } else {
                membershipGroup.style.display = 'none';
                document.getElementById('membershipType').removeAttribute('required');
            }
            
            modal.style.display = 'flex';
        }
        
        // Exercise counter for workout plan
        let exerciseCount = 1;
        
        function addExercise() {
            const container = document.getElementById('exercisesContainer');
            const newExercise = document.createElement('div');
            newExercise.className = 'exercise-entry';
            newExercise.innerHTML = `
                <div class="form-group">
                    <label>Exercise Name</label>
                    <input type="text" name="exercises[${exerciseCount}][name]" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label>Sets</label>
                        <input type="number" name="exercises[${exerciseCount}][sets]" required>
                    </div>
                    <div class="form-group">
                        <label>Reps</label>
                        <input type="text" name="exercises[${exerciseCount}][reps]" required>
                    </div>
                    <div class="form-group">
                        <label>Weight</label>
                        <input type="text" name="exercises[${exerciseCount}][weight]">
                    </div>
                    <div class="form-group">
                        <label>Day</label>
                        <select name="exercises[${exerciseCount}][day]">
                            <option value="monday">Monday</option>
                            <option value="tuesday">Tuesday</option>
                            <option value="wednesday">Wednesday</option>
                            <option value="thursday">Thursday</option>
                            <option value="friday">Friday</option>
                            <option value="saturday">Saturday</option>
                            <option value="sunday">Sunday</option>
                        </select>
                    </div>
                </div>
                <button type="button" class="btn" onclick="this.parentElement.remove()" style="background: var(--danger); color: white; margin-top: 10px;">
                    <i class="fas fa-trash"></i> Remove Exercise
                </button>
            `;
            container.appendChild(newExercise);
            exerciseCount++;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // CTA button functionality
        document.querySelector('.cta-button')?.addEventListener('click', function() {
            openModal('registerModal');
        });
    </script>
        
</body>
</html>
<?php
// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ".str_replace('?logout=1', '', $_SERVER['PHP_SELF']));
    exit;
}

// Clear setup session data
if (isset($_GET['clear_setup_session'])) {
    unset($_SESSION['show_setup_popup']);
    unset($_SESSION['setup_user_data']);
    exit;
}
?>