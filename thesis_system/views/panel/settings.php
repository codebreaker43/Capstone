<?php
// PANEL MEMBER SETTINGS VIEW
// Modern CSU CEIT styled settings matching Secretariat layout
?>

<div class="banner" style="background: linear-gradient(135deg, #047857 0%, #065F46 50%, #064E3B 100%); border-radius: 16px; margin: 1.5rem 2rem 1.25rem; padding: 2rem 2.5rem; color: white; position: relative; overflow: hidden;">
    <div style="position: absolute; top: -30%; right: -5%; width: 200px; height: 200px; background: rgba(255,255,255,0.05); border-radius: 50%;"></div>
    <h1 style="font-size: 1.6rem; font-weight: 800; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem;">
        <i class="fa-solid fa-gear"></i> Account Settings
    </h1>
    <p style="font-size: 0.9rem; opacity: 0.85; margin: 0;">Manage your panel evaluator profile, areas of expertise, and security preferences.</p>
</div>

<!-- Settings Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin: 0 2rem 2rem;">
    
    <!-- Left: Personal Information -->
    <div class="card" style="border-radius: 14px;">
        <div class="card-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid #F1F5F9;">
            <i class="fa-solid fa-user-pen" style="color: #047857;"></i>
            <h2 style="font-size: 1rem; font-weight: 700; margin: 0;">Personal Information</h2>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <form method="POST" action="action.php">
                <input type="hidden" name="update_settings" value="1">
                
                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="flex: 1;">
                        <label style="font-size: 0.82rem; font-weight: 600; color: #475569; margin-bottom: 0.35rem; display: block;">First Name</label>
                        <input type="text" name="firstname" value="<?php echo htmlspecialchars($user_data['firstname'] ?? ''); ?>" required 
                               style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #E2E8F0; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem;">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label style="font-size: 0.82rem; font-weight: 600; color: #475569; margin-bottom: 0.35rem; display: block;">Last Name</label>
                        <input type="text" name="lastname" value="<?php echo htmlspecialchars($user_data['lastname'] ?? ''); ?>" required
                               style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #E2E8F0; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.82rem; font-weight: 600; color: #475569; margin-bottom: 0.35rem; display: block;">Email Address (Gmail / University Email) <span style="color: #EF4444;">*</span></label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required
                           placeholder="e.g. yourname@gmail.com or name@csucc.edu.ph"
                           style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #E2E8F0; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; background: white; color: #0F172A;">
                    <span style="font-size: 0.72rem; color: #64748B; margin-top: 0.2rem; display: block;"><i class="fa-solid fa-circle-info" style="color: #047857;"></i> You can use your Gmail or institutional CSU email address.</span>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.82rem; font-weight: 600; color: #475569; margin-bottom: 0.35rem; display: block;">Date of Birth</label>
                    <input type="date" name="dob" value="<?php echo htmlspecialchars($user_data['dob'] ?? ''); ?>"
                           style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #E2E8F0; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem;">
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.82rem; font-weight: 600; color: #475569; margin-bottom: 0.35rem; display: block;">Phone Number</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" placeholder="e.g. 09123456789"
                           style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #E2E8F0; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem;">
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label style="font-size: 0.82rem; font-weight: 600; color: #475569; margin-bottom: 0.35rem; display: block;">Address</label>
                    <input type="text" name="address" value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>" placeholder="Street / Barangay / City / Municipality"
                           style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #E2E8F0; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem;">
                </div>

                <button type="submit" style="background: linear-gradient(135deg, #047857, #065F46); color: white; border: none; padding: 0.65rem 1.5rem; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.2s ease;"
                        onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(4,120,87,0.3)'"
                        onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <i class="fa-solid fa-floppy-disk"></i> Save Personal Info
                </button>
            </form>
        </div>
    </div>

    <!-- Right Column: Panel Details + Password + Account Info -->
    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
        
        <!-- Panel Profile -->
        <div class="card" style="border-radius: 14px;">
            <div class="card-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid #F1F5F9;">
                <i class="fa-solid fa-users" style="color: #047857;"></i>
                <h2 style="font-size: 1rem; font-weight: 700; margin: 0;">Panel & Evaluation Profile</h2>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" action="action.php">
                    <input type="hidden" name="update_panel_profile" value="1">

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label style="font-size: 0.82rem; font-weight: 600; color: #475569; margin-bottom: 0.35rem; display: block;">Department</label>
                        <select name="department" style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #E2E8F0; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; background: white;">
                            <?php 
                            $curr_dept = $profile['department'] ?? 'Information Technology';
                            $depts = ['Information Technology', 'Computer Engineering', 'Electrical Engineering'];
                            foreach($depts as $d): ?>
                                <option value="<?php echo $d; ?>" <?php echo $curr_dept === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label style="font-size: 0.82rem; font-weight: 600; color: #475569; margin-bottom: 0.35rem; display: block;">Evaluation Expertise & Specialization</label>
                        <input type="text" name="expertise" value="<?php echo htmlspecialchars($profile['expertise'] ?? ''); ?>" placeholder="e.g. Systems Architecture, Network Security, Mobile Apps"
                               style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #E2E8F0; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem;">
                        <span style="font-size: 0.72rem; color: #94A3B8; margin-top: 0.2rem; display: block;">Used by the secretariat and admins to match you with relevant defense titles.</span>
                    </div>

                    <button type="submit" style="background: linear-gradient(135deg, #047857, #065F46); color: white; border: none; padding: 0.65rem 1.5rem; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.2s ease;"
                            onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(4,120,87,0.3)'"
                            onmouseout="this.style.transform=''; this.style.boxShadow=''">
                        <i class="fa-solid fa-user-check"></i> Update Evaluation Profile
                    </button>
                </form>
            </div>
        </div>

        <!-- Security / Password Change -->
        <div class="card" style="border-radius: 14px;">
            <div class="card-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid #F1F5F9;">
                <i class="fa-solid fa-shield-halved" style="color: #D97706;"></i>
                <h2 style="font-size: 1rem; font-weight: 700; margin: 0;">Security</h2>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" action="action.php">
                    <input type="hidden" name="update_settings" value="1">

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label style="font-size: 0.82rem; font-weight: 600; color: #475569; margin-bottom: 0.35rem; display: block;">New Password</label>
                        <input type="password" name="new_password" placeholder="Enter new password (leave blank to keep current)"
                               style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #E2E8F0; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem;">
                        <span style="font-size: 0.72rem; color: #94A3B8; margin-top: 0.2rem; display: block;">Leave blank if you do not want to change your password.</span>
                    </div>

                    <button type="submit" style="background: linear-gradient(135deg, #D97706, #B45309); color: white; border: none; padding: 0.65rem 1.5rem; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.2s ease;"
                            onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(217,119,6,0.3)'"
                            onmouseout="this.style.transform=''; this.style.boxShadow=''">
                        <i class="fa-solid fa-key"></i> Update Password
                    </button>
                </form>
            </div>
        </div>

        <!-- Account Info Card -->
        <div class="card" style="border-radius: 14px; background: #F8FAFC;">
            <div class="card-body" style="padding: 1.25rem 1.5rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #D97706, #B45309); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem; font-weight: 800;">
                        <?php echo strtoupper(substr($name, 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-size: 0.95rem; font-weight: 700; color: #1E293B;"><?php echo htmlspecialchars($name); ?></div>
                        <div style="font-size: 0.8rem; color: #64748B;"><?php echo htmlspecialchars($user_data['email'] ?? ''); ?></div>
                        <div style="display: flex; gap: 0.5rem; margin-top: 0.35rem;">
                            <span style="background: #FEF3C7; color: #B45309; font-size: 0.68rem; font-weight: 700; padding: 2px 8px; border-radius: 6px; border: 1px solid #FDE68A;">
                                <i class="fa-solid fa-users"></i> Panel Member
                            </span>
                            <span style="background: #F0FDF4; color: #065F46; font-size: 0.68rem; font-weight: 600; padding: 2px 8px; border-radius: 6px;">
                                <i class="fa-solid fa-circle-check"></i> Active
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
        margin: 0 1rem 1.5rem !important;
    }
}
</style>
