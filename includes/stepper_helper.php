<?php
/**
 * FixMyCampus - Interactive Issue Stepper Component Helper
 * Renders modern progress stepper matching shadcn / 21st.dev style
 */

function renderIssueStepper($issue, $history = []) {
    $status = $issue['status'] ?? 'pending';
    $isAssigned = !empty($issue['assigned_to']);
    $isRejected = ($status === 'rejected');

    // Determine current step index (0 to 4)
    // 0: Submitted
    // 1: Assigned / Review
    // 2: In Progress
    // 3: Resolved
    // 4: Closed
    $stepIndex = 0;
    if ($status === 'closed') {
        $stepIndex = 4;
    } elseif ($status === 'resolved') {
        $stepIndex = 3;
    } elseif ($status === 'in_progress') {
        $stepIndex = 2;
    } elseif ($isAssigned) {
        $stepIndex = 1;
    } else {
        $stepIndex = 0;
    }

    $fillPercent = [
        0 => '0%',
        1 => '25%',
        2 => '50%',
        3 => '75%',
        4 => '100%'
    ][$stepIndex] ?? '0%';

    if ($isRejected) {
        $fillPercent = '25%';
    }

    $steps = [
        [
            'id' => 'submitted',
            'label' => 'Submitted',
            'icon' => 'bi-file-earmark-text',
            'time' => !empty($issue['created_at']) ? date('d M, h:i A', strtotime($issue['created_at'])) : ''
        ],
        [
            'id' => 'assigned',
            'label' => 'Under Review',
            'icon' => 'bi-person-check',
            'time' => $isAssigned ? 'Assigned' : 'Pending'
        ],
        [
            'id' => 'in_progress',
            'label' => 'In Progress',
            'icon' => 'bi-tools',
            'time' => ($stepIndex >= 2) ? 'Active' : ''
        ],
        [
            'id' => 'resolved',
            'label' => 'Resolved',
            'icon' => 'bi-check-circle',
            'time' => ($stepIndex >= 3) ? 'Work Finished' : ''
        ],
        [
            'id' => 'closed',
            'label' => 'Closed',
            'icon' => 'bi-patch-check-fill',
            'time' => ($stepIndex >= 4) ? 'Verified' : ''
        ]
    ];

    ob_start();
    ?>
    <div class="panel spotlight-card border-beam-card" style="margin-bottom: 20px; padding: 22px 24px; overflow: hidden;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-weight:600;font-size:14px;letter-spacing:0.02em;text-transform:uppercase;color:var(--text-muted);">
            Progress Tracking
          </span>
        </div>
        <span class="badge <?= $isRejected ? 'badge-rose' : ($stepIndex === 4 ? 'badge-emerald' : 'badge-amber') ?>">
          <?= $isRejected ? 'Rejected' : ucwords(str_replace('_', ' ', $status)) ?>
        </span>
      </div>

      <div class="issue-stepper" style="position:relative;">
        <div class="stepper-line" style="position:absolute;top:19px;left:5%;width:90%;height:3px;background:rgba(255,255,255,0.08);z-index:1;border-radius:2px;">
          <div class="stepper-line-fill" style="width: <?= $fillPercent ?>;height:100%;background:linear-gradient(90deg, #10b981, #6366f1);border-radius:2px;transition:width 0.6s cubic-bezier(0.16,1,0.3,1);"></div>
        </div>

        <?php foreach ($steps as $idx => $s): 
          $stepClass = '';
          if ($isRejected && $idx === 1) {
              $stepClass = 'rejected';
          } elseif ($idx < $stepIndex) {
              $stepClass = 'complete';
          } elseif ($idx === $stepIndex) {
              $stepClass = 'current';
          }
        ?>
          <div class="stepper-step <?= $stepClass ?>">
            <div class="stepper-circle">
              <?php if ($stepClass === 'complete'): ?>
                <i class="bi bi-check-lg" style="font-size:18px;"></i>
              <?php elseif ($isRejected && $idx === 1): ?>
                <i class="bi bi-x-lg" style="font-size:16px;"></i>
              <?php else: ?>
                <i class="bi <?= $s['icon'] ?>" style="font-size:15px;"></i>
              <?php endif; ?>
            </div>
            <div class="stepper-label"><?= $s['label'] ?></div>
            <?php if (!empty($s['time'])): ?>
              <div class="stepper-time"><?= htmlspecialchars($s['time']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
