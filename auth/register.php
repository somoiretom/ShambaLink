<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$errors = [];
$formData = [
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'phone' => '',
    'role' => 'farmer',
    'farm_name' => '',
    'farm_location' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'email' => sanitize($_POST['email'] ?? ''),
        'first_name' => sanitize($_POST['first_name'] ?? ''),
        'last_name' => sanitize($_POST['last_name'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'role' => sanitize($_POST['role'] ?? 'farmer'),
        'farm_name' => sanitize($_POST['farm_name'] ?? ''),
        'farm_location' => sanitize($_POST['farm_location'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'csrf_token' => $_POST['csrf_token'] ?? ''
    ];

    // CSRF Protection
    if (!verifyCSRFToken($formData['csrf_token'])) {
        $errors['system'] = 'Invalid CSRF token';
    }

    // Validation
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,15}$/', $formData['password'])) {
        $errors['password'] = 'Password must be 8-15 chars with uppercase, lowercase, number and special char';
    }

    if ($formData['password'] !== $formData['confirm_password']) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    if ($formData['role'] === 'farmer' && empty($formData['farm_name'])) {
        $errors['farm_name'] = 'Farm name is required for farmers';
    }

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$formData['email']]);
    if ($stmt->fetch()) {
        $errors['email'] = 'Email already registered';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Set is_approved based on role (consumers auto-approved)
            $isApproved = $formData['role'] === 'consumer' ? 1 : 0;

            // Insert user
            $stmt = $pdo->prepare("
                INSERT INTO users 
                (email, password, first_name, last_name, phone, role, farm_name, farm_location, is_approved) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $hashedPassword = password_hash($formData['password'], PASSWORD_BCRYPT);
            $stmt->execute([
                $formData['email'],
                $hashedPassword,
                $formData['first_name'],
                $formData['last_name'],
                $formData['phone'],
                $formData['role'],
                $formData['farm_name'],
                $formData['farm_location'],
                $isApproved
            ]);

            $userId = $pdo->lastInsertId();

            // Insert farmer-specific data if role is farmer
            if ($formData['role'] === 'farmer') {
                $stmt = $pdo->prepare("
                    INSERT INTO farmer_profiles 
                    (user_id, farming_method, bio) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    sanitize($_POST['farming_method'] ?? ''),
                    sanitize($_POST['bio'] ?? '')
                ]);
            }

            $pdo->commit();

            // If consumer, log them in directly
            if ($formData['role'] === 'consumer') {
                completeLogin([
                    'id' => $userId,
                    'email' => $formData['email'],
                    'role' => $formData['role'],
                    'first_name' => $formData['first_name'],
                    'last_name' => $formData['last_name']
                ]);
                header("Location: " . BASE_URL . "/user/dashboard.php");
                exit();
            } else {
                // For farmers, show pending approval message
                $_SESSION['registration_success'] = true;
                $_SESSION['pending_approval'] = true;
                header("Location: " . BASE_URL . "/login.php");
                exit();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Registration error: " . $e->getMessage());
            $errors['system'] = 'Registration failed. Please try again.';
        }
    }
}

$page_title = "Register";
$custom_css = BASE_URL . "/assets/css/auth.css";
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Farmer Platform" class="auth-logo">
            <h1>Create Account</h1>
            <p>Join our agricultural community</p>
        </div>

        <?php if (!empty($errors['system'])): ?>
            <div class="alert alert-error">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                <span><?= htmlspecialchars($errors['system']) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form" id="registrationForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <div class="form-group">
                <label for="role">I am registering as:</label>
                <select id="role" name="role" class="role-select" required>
                    <option value="farmer" <?= $formData['role'] === 'farmer' ? 'selected' : '' ?>>Farmer/Producer</option>
                    <option value="consumer" <?= $formData['role'] === 'consumer' ? 'selected' : '' ?>>Consumer/Buyer</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" 
                           value="<?= htmlspecialchars($formData['first_name']) ?>" required>
                    <?php if (!empty($errors['first_name'])): ?>
                        <span class="error-message"><?= htmlspecialchars($errors['first_name']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" 
                           value="<?= htmlspecialchars($formData['last_name']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" 
                       value="<?= htmlspecialchars($formData['email']) ?>" required>
                <?php if (!empty($errors['email'])): ?>
                    <span class="error-message"><?= htmlspecialchars($errors['email']) ?></span>
                <?php endif; ?>
                <small class="hint">We'll never share your email</small>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" 
                       value="<?= htmlspecialchars($formData['phone']) ?>">
                <small class="hint">Optional - for order notifications</small>
            </div>

            <div id="farmerFields" style="<?= $formData['role'] === 'consumer' ? 'display: none;' : '' ?>">
                <div class="form-group">
                    <label for="farm_name">Farm/Business Name *</label>
                    <input type="text" id="farm_name" name="farm_name" 
                           value="<?= htmlspecialchars($formData['farm_name']) ?>">
                    <?php if (!empty($errors['farm_name'])): ?>
                        <span class="error-message"><?= htmlspecialchars($errors['farm_name']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="farm_location">Location</label>
                    <input type="text" id="farm_location" name="farm_location" 
                           value="<?= htmlspecialchars($formData['farm_location']) ?>">
                    <small class="hint">City/Region where your farm is located</small>
                </div>

                <div class="form-group">
                    <label for="farming_method">Primary Farming Method</label>
                    <select id="farming_method" name="farming_method">
                        <option value="">Select...</option>
                        <option value="organic">Organic</option>
                        <option value="conventional">Conventional</option>
                        <option value="permaculture">Permaculture</option>
                        <option value="hydroponics">Hydroponics</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="bio">About Your Farm</label>
                    <textarea id="bio" name="bio" rows="3" 
                              placeholder="Tell us about your farming practices, certifications, etc."></textarea>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Create Password *</label>
                <div class="password-input">
                    <input type="password" id="password" name="password" required
                           placeholder="8-15 characters" 
                           pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,15}$">
                    <button type="button" class="toggle-password" aria-label="Show password">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                        </svg>
                    </button>
                </div>
                <div class="password-strength"></div>
                <?php if (!empty($errors['password'])): ?>
                    <span class="error-message"><?= htmlspecialchars($errors['password']) ?></span>
                <?php endif; ?>
                <small class="hint">Must include uppercase, lowercase, number, and special character</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <div class="password-input">
                    <input type="password" id="confirm_password" name="confirm_password" required
                           placeholder="Re-enter your password">
                    <button type="button" class="toggle-password" aria-label="Show password">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                        </svg>
                    </button>
                </div>
                <?php if (!empty($errors['confirm_password'])): ?>
                    <span class="error-message"><?= htmlspecialchars($errors['confirm_password']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">
                    <span>Create Account</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M10 17l5-5-5-5v10z"/>
                    </svg>
                </button>
            </div>

            <div class="auth-footer">
                <p>By registering, you agree to our <a href="<?= BASE_URL ?>/terms" class="text-link">Terms of Service</a> and <a href="<?= BASE_URL ?>/privacy" class="text-link">Privacy Policy</a></p>
                <p>Already have an account? <a href="<?= BASE_URL ?>/auth/login.php" class="text-link">Sign in</a></p>
            </div>
            
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.classList.toggle('active');
            this.setAttribute('aria-label', 
                type === 'password' ? 'Show password' : 'Hide password');
        });
    });

    // Password strength indicator
    const passwordInput = document.getElementById('password');
    const strengthIndicator = document.createElement('div');
    strengthIndicator.className = 'password-strength';
    passwordInput.parentNode.insertBefore(strengthIndicator, passwordInput.nextSibling);

    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        // Length check
        if (password.length > 7) strength++;
        if (password.length > 11) strength++;
        
        // Complexity checks
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        // Update indicator
        strengthIndicator.className = 'password-strength';
        if (password.length > 0) {
            if (strength < 3) {
                strengthIndicator.classList.add('weak');
                strengthIndicator.textContent = 'Weak';
            } else if (strength < 5) {
                strengthIndicator.classList.add('medium');
                strengthIndicator.textContent = 'Medium';
            } else {
                strengthIndicator.classList.add('strong');
                strengthIndicator.textContent = 'Strong';
            }
        } else {
            strengthIndicator.textContent = '';
        }
    });

    // Role selection toggle
    const roleSelect = document.getElementById('role');
    const farmerFields = document.getElementById('farmerFields');
    const farmNameInput = document.getElementById('farm_name');
    
    roleSelect.addEventListener('change', function() {
        if (this.value === 'farmer') {
            farmerFields.style.display = 'block';
            farmNameInput.required = true;
        } else {
            farmerFields.style.display = 'none';
            farmNameInput.required = false;
        }
    });

    // Form validation
    const form = document.getElementById('registrationForm');
    form.addEventListener('submit', function(e) {
        let valid = true;
        
        // Password validation
        const password = document.getElementById('password').value;
        const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,15}$/;
        
        if (!passwordPattern.test(password)) {
            alert('Password must be 8-15 characters with at least one uppercase, lowercase, number and special character');
            valid = false;
        }
        
        // Confirm password
        if (password !== document.getElementById('confirm_password').value) {
            alert('Passwords do not match');
            valid = false;
        }
        
        // Farmer-specific validation
        if (roleSelect.value === 'farmer' && 
            document.getElementById('farm_name').value.trim() === '') {
            alert('Farm name is required for farmers');
            valid = false;
        }
        
        if (!valid) {
            e.preventDefault();
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>