<?php
/**
 * Create User and Token Form
 * This page allows administrators to create new users and automatically generate tokens for them
 */

// Add to admin menu
function tgtinfo_create_user_menu() {
    add_submenu_page(
        'tgtinfo-admin/tgtinfo-reports.php',
        'Create User & Token',
        'Create User & Token',
        'manage_options',
        'tgtinfo-create-user',
        'tgtinfo_create_user_page'
    );
}
add_action('admin_menu', 'tgtinfo_create_user_menu', 20);

// Display the create user form
function tgtinfo_create_user_page() {
    global $wpdb;
    
    $success_msg = '';
    $error_msg = '';
    
    // Handle form submission
    if (isset($_POST['create_user_token']) && check_admin_referer('tgtinfo_create_user_nonce', 'tgtinfo_create_user_nonce_field')) {
        
        // Sanitize inputs
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $plan = sanitize_text_field($_POST['plan']);
        $website_url = esc_url_raw($_POST['website_url']);
        $num_tokens = intval($_POST['num_tokens']);
        $is_trial = isset($_POST['is_trial']);
        
        // Validate required fields
        if (empty($username) || empty($email) || empty($password)) {
            $error_msg = 'Username, email, and password are required fields.';
        } else {
            // Check if user already exists
            if (username_exists($username)) {
                $error_msg = 'Username already exists. Please choose a different username.';
            } elseif (email_exists($email)) {
                $error_msg = 'Email already exists in the system.';
            } else {
                // Create the WordPress user
                $user_id = wp_create_user($username, $password, $email);
                
                if (is_wp_error($user_id)) {
                    $error_msg = 'Error creating user: ' . $user_id->get_error_message();
                } else {
                    // Update user meta
                    if (!empty($first_name)) {
                        update_user_meta($user_id, 'first_name', $first_name);
                    }
                    if (!empty($last_name)) {
                        update_user_meta($user_id, 'last_name', $last_name);
                    }
                    
                    // Set user role
                    $user = new WP_User($user_id);
                    $user->set_role('subscriber');
                    
                    // Set plan type
                    $plan_full = $plan;
                    if ($is_trial) {
                        $plan_full .= ' Trial';
                    }
                    update_user_meta($user_id, 'tgtinfo_plan', $plan_full);
                    
                    // Set plan limits based on plan type
                    switch ($plan) {
                        case 'Individual Plan':
                            update_user_meta($user_id, 'tgtinfo_max_topic', 1);
                            update_user_meta($user_id, 'tgtinfo_max_sites', 1);
                            update_user_meta($user_id, 'tgtinfo_max_notebk', 1);
                            update_user_meta($user_id, 'tgtinfo_max_source', 10);
                            $product = 'MyCurator Ind';
                            break;
                        case 'Business Plan':
                            update_user_meta($user_id, 'tgtinfo_max_topic', 0); // 0 = unlimited
                            update_user_meta($user_id, 'tgtinfo_max_sites', 6);
                            update_user_meta($user_id, 'tgtinfo_max_notebk', 0);
                            update_user_meta($user_id, 'tgtinfo_max_source', 0);
                            $product = 'MyCurator Bus';
                            break;
                        case 'Pro Plan':
                            update_user_meta($user_id, 'tgtinfo_max_topic', 6);
                            update_user_meta($user_id, 'tgtinfo_max_sites', 0);
                            update_user_meta($user_id, 'tgtinfo_max_notebk', 0);
                            update_user_meta($user_id, 'tgtinfo_max_source', 0);
                            $product = 'MyCurator Pro';
                            break;
                    }
                    
                    // Generate tokens
                    $tokens = array();
                    $token_list = array();
                    
                    for ($i = 0; $i < $num_tokens; $i++) {
                        $newtoken = md5('tgtinfo-' . $email . strval(rand(100, 10000)) . time() . $i);
                        $tokens[] = $newtoken;
                        $token_list[] = $newtoken;
                        
                        // Insert into wp_cs_validate table
                        $insert_data = array(
                            'token' => $newtoken,
                            'user_id' => $user_id,
                            'product' => $product
                        );
                        
                        // Add end date for trials
                        if ($is_trial) {
                            $insert_data['end_date'] = date('Y-m-d', strtotime('+30 days'));
                        }
                        
                        $wpdb->insert('wp_cs_validate', $insert_data);
                    }
                    
                    // Save tokens to user meta
                    update_user_meta($user_id, 'tgtinfo_apikey', $tokens);
                    
                    // Create a payment record
                    $payment_details = array(
                        'post_author' => $user_id,
                        'post_title' => $first_name . ' ' . $last_name,
                        'post_type' => 'edd_payment',
                        'post_status' => 'publish'
                    );
                    $post_id = wp_insert_post($payment_details);
                    
                    update_post_meta($post_id, 'tgtinfo_plan', $plan_full);
                    update_post_meta($post_id, 'tgtinfo_amount', 0);
                    update_post_meta($post_id, 'tgtinfo_paypaltrx', 'Admin Created: ' . date('Y-m-d H:i:s'));
                    
                    // Success message with token information
                    $success_msg = '<strong>User created successfully!</strong><br>';
                    $success_msg .= 'User ID: ' . $user_id . '<br>';
                    $success_msg .= 'Username: ' . $username . '<br>';
                    $success_msg .= 'Email: ' . $email . '<br>';
                    $success_msg .= 'Plan: ' . $plan_full . '<br>';
                    $success_msg .= '<br><strong>Generated Token(s):</strong><br>';
                    foreach ($token_list as $idx => $token) {
                        $success_msg .= 'Token ' . ($idx + 1) . ': <code>' . $token . '</code><br>';
                    }
                }
            }
        }
    }
    
    // Display the form
    ?>
    <div class="wrap">
        <h1>Create User and Token</h1>
        
        <?php if (!empty($success_msg)) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo $success_msg; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo $error_msg; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="postbox-container" style="max-width: 800px;">
            <div class="postbox">
                <div class="inside">
                    <form method="post" action="">
                        <?php wp_nonce_field('tgtinfo_create_user_nonce', 'tgtinfo_create_user_nonce_field'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="username">Username *</label></th>
                                <td>
                                    <input type="text" name="username" id="username" class="regular-text" required>
                                    <p class="description">Login username for the new user</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="email">Email *</label></th>
                                <td>
                                    <input type="email" name="email" id="email" class="regular-text" required>
                                    <p class="description">Email address for the new user</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="password">Password *</label></th>
                                <td>
                                    <input type="password" name="password" id="password" class="regular-text" required>
                                    <p class="description">Password for the new user</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="first_name">First Name</label></th>
                                <td>
                                    <input type="text" name="first_name" id="first_name" class="regular-text">
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="last_name">Last Name</label></th>
                                <td>
                                    <input type="text" name="last_name" id="last_name" class="regular-text">
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="website_url">Website URL</label></th>
                                <td>
                                    <input type="url" name="website_url" id="website_url" class="regular-text" placeholder="https://example.com">
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="plan">Plan Type *</label></th>
                                <td>
                                    <select name="plan" id="plan" required>
                                        <option value="">-- Select Plan --</option>
                                        <option value="Individual Plan">Individual Plan</option>
                                        <option value="Business Plan">Business Plan</option>
                                        <option value="Pro Plan">Pro Plan</option>
                                    </select>
                                    <p class="description">Select the subscription plan for this user</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="num_tokens">Number of Tokens *</label></th>
                                <td>
                                    <input type="number" name="num_tokens" id="num_tokens" value="1" min="1" max="10" required>
                                    <p class="description">Number of API tokens to generate (1-10)</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="is_trial">Trial Account</label></th>
                                <td>
                                    <input type="checkbox" name="is_trial" id="is_trial" value="1">
                                    <label for="is_trial">This is a trial account (30 days)</label>
                                    <p class="description">Check this box to create a trial account with a 30-day expiration</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="create_user_token" class="button button-primary" value="Create User and Generate Token(s)">
                        </p>
                    </form>
                </div>
            </div>
            
            <div class="postbox">
                <h3 class="hndle"><span>Plan Limits Reference</span></h3>
                <div class="inside">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Plan</th>
                                <th>Max Topics</th>
                                <th>Max Sites</th>
                                <th>Max Notebooks</th>
                                <th>Max Sources</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Individual</strong></td>
                                <td>1</td>
                                <td>1</td>
                                <td>1</td>
                                <td>10</td>
                            </tr>
                            <tr>
                                <td><strong>Business</strong></td>
                                <td>Unlimited (0)</td>
                                <td>6</td>
                                <td>Unlimited (0)</td>
                                <td>Unlimited (0)</td>
                            </tr>
                            <tr>
                                <td><strong>Pro</strong></td>
                                <td>6</td>
                                <td>Unlimited (0)</td>
                                <td>Unlimited (0)</td>
                                <td>Unlimited (0)</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="description">Note: 0 means unlimited</p>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .postbox { margin-top: 20px; }
        .postbox .inside { padding: 20px; }
        .form-table th { width: 200px; }
        code { 
            background: #f0f0f0; 
            padding: 2px 6px; 
            border-radius: 3px;
            font-size: 13px;
            word-break: break-all;
        }
    </style>
    <?php
}
