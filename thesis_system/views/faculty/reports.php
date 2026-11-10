<div class="banner">
    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
            <h1><i class="fa-solid fa-file-pdf"></i> Advisee Reports</h1>
            <p>Generate and print your advisee roster.</p>
        </div>
        <button onclick="window.print()" class="btn-primary" style="background: white; color: var(--primary-blue);"><i class="fa-solid fa-print"></i> Print Roster</button>
    </div>
</div>

<div class="card" id="printable-area">
    <div class="card-header">
        <i class="fa-solid fa-clipboard-list"></i>
        <h2>My Advisee Roster</h2>
    </div>
    <div class="card-body">
        <div style="text-align: center; margin-bottom: 2rem; display: none;" class="print-header">
            <h2 style="color: black;">Interactive Platform for Capstone Project Oral Defense</h2>
            <p style="color: black;">Faculty Advisee Roster - Prof. <?php echo htmlspecialchars($name); ?></p>
            <hr style="margin-top: 1rem; border: 1px solid #000;">
        </div>
        
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <tr style="background: #F8FAFC;">
                <th style="padding: 1rem; border-bottom: 2px solid #E2E8F0; color: #475569;">Student Name</th>
                <th style="padding: 1rem; border-bottom: 2px solid #E2E8F0; color: #475569;">Email</th>
                <th style="padding: 1rem; border-bottom: 2px solid #E2E8F0; color: #475569;">Capstone Topic</th>
            </tr>
            <?php 
            $advisees_res->data_seek(0); // Reset pointer
            if($advisees_res->num_rows == 0):
            ?>
            <tr><td colspan="3" style="text-align: center; padding: 2rem; color: #64748B;">No active advisees found.</td></tr>
            <?php else:
            while($adv = $advisees_res->fetch_assoc()): 
            ?>
            <tr style="border-bottom: 1px solid #E2E8F0;">
                <td style="padding: 1rem; font-weight: 600; color: #1E293B;"><?php echo htmlspecialchars($adv['stu_name']); ?></td>
                <td style="padding: 1rem; color: #64748B;"><?php echo htmlspecialchars($adv['email']); ?></td>
                <td style="padding: 1rem; color: #64748B;"><?php echo htmlspecialchars($adv['thesis_topic']); ?></td>
            </tr>
            <?php 
            endwhile; 
            endif;
            ?>
        </table>
        <div style="margin-top: 2rem; padding-top: 1rem; border-top: 2px dashed #E2E8F0;" class="print-only">
            <p style="text-align: right; color: black;"><strong>Total Advisees:</strong> <?php echo $advisees_res->num_rows; ?> / <?php echo $profile['capacity']; ?></p>
            <br><br>
            <p style="text-align: right; color: black;">_________________________<br>Signature over printed name</p>
        </div>
    </div>
</div>
