<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label">Gateway Name *</label>
            <input type="text" name="gateway_name" class="form-control" 
                   value="<?= $gateway ? htmlspecialchars($gateway['name']) : '' ?>" 
                   placeholder="e.g., PayPal, Bitcoin, Bank Transfer" required>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label">Gateway Type *</label>
            <select name="gateway_type" class="form-select" required>
                <option value="">Select Type</option>
                <option value="PayPal" <?= $gateway && $gateway['type'] === 'PayPal' ? 'selected' : '' ?>>PayPal</option>
                <option value="Bank Transfer" <?= $gateway && $gateway['type'] === 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                <option value="Bitcoin" <?= $gateway && $gateway['type'] === 'Bitcoin' ? 'selected' : '' ?>>Bitcoin/Crypto</option>
                <option value="UPI" <?= $gateway && $gateway['type'] === 'UPI' ? 'selected' : '' ?>>UPI (India)</option>
                <option value="Paytm" <?= $gateway && $gateway['type'] === 'Paytm' ? 'selected' : '' ?>>Paytm</option>
                <option value="Western Union" <?= $gateway && $gateway['type'] === 'Western Union' ? 'selected' : '' ?>>Western Union</option>
                <option value="Payoneer" <?= $gateway && $gateway['type'] === 'Payoneer' ? 'selected' : '' ?>>Payoneer</option>
                <option value="Skrill" <?= $gateway && $gateway['type'] === 'Skrill' ? 'selected' : '' ?>>Skrill</option>
                <option value="Other" <?= $gateway && $gateway['type'] === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Minimum Amount ($) *</label>
            <input type="number" name="min_amount" class="form-control" 
                   value="<?= $gateway ? $gateway['min_amount'] : '5.00' ?>" 
                   step="0.01" min="0" required>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Maximum Amount ($) *</label>
            <input type="number" name="max_amount" class="form-control" 
                   value="<?= $gateway ? $gateway['max_amount'] : '10000.00' ?>" 
                   step="0.01" min="0" required>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Icon (FontAwesome)</label>
            <input type="text" name="icon" class="form-control" 
                   value="<?= $gateway ? htmlspecialchars($gateway['icon']) : 'fa-wallet' ?>" 
                   placeholder="fa-wallet">
            <small class="text-muted">Use FontAwesome class names</small>
        </div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Processing Time</label>
    <input type="text" name="processing_time" class="form-control" 
           value="<?= $gateway ? htmlspecialchars($gateway['processing_time']) : '1-3 business days' ?>" 
           placeholder="1-3 business days">
</div>

<div class="mb-3">
    <label class="form-label">Required Fields (one per line)</label>
    <textarea name="required_fields[]" class="form-control" rows="5" 
              placeholder="Email&#10;Full Name&#10;Account Number&#10;Bank Name"><?php
    if ($gateway && $gateway['required_fields']) {
        $fields = json_decode($gateway['required_fields'], true);
        if (is_array($fields)) {
            echo implode("\n", $fields);
        }
    }
    ?></textarea>
    <small class="text-muted">These fields will be shown to users when requesting withdrawal</small>
</div>

<div class="mb-3">
    <label class="form-label">Instructions for Users</label>
    <textarea name="instructions" class="form-control" rows="4" 
              placeholder="Enter withdrawal instructions here..."><?= $gateway ? htmlspecialchars($gateway['instructions']) : '' ?></textarea>
</div>

<div class="form-check">
    <input class="form-check-input" type="checkbox" name="is_active" id="isActive" 
           <?= !$gateway || $gateway['is_active'] ? 'checked' : '' ?>>
    <label class="form-check-label" for="isActive">
        Active (Users can use this gateway for withdrawals)
    </label>
</div>
