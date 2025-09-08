<style>
    /* Modal background */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }

    /* Modal box */
    .modal-content {
        background: #fff;
        padding: 25px 30px;
        border-radius: 12px;
        width: 500px;
        max-width: 95%;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        animation: fadeIn 0.3s ease;
    }

    /* Modal title */
    .modal-content h3 {
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
        font-weight: 600;
        color: #333;
        text-align: center;
    }

    /* Form styles */
    .modal-form .form-group {
        margin-bottom: 15px;
    }

    .modal-form label {
        display: block;
        margin-bottom: 6px;
        font-size: 14px;
        font-weight: 500;
        color: #444;
        min-width: 100px;
    }

    .modal-form input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.2s ease;
    }

    .modal-form input:focus {
        border-color: #1e88e5;
        outline: none;
    }

    /* Buttons */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }

    .btn {
        padding: 10px 20px;
        background: #1e88e5;
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .btn:hover {
        background: #1565c0;
    }

    .btn-clear {
        background: #eee;
        color: #333;
    }

    .btn-clear:hover {
        background: #ddd;
    }

    /* Hidden utility */
    .hidden {
        display: none !important;
    }

    /* Animation */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* Error messages */
    .error-message {
        color: #e53935;
        position: absolute;
        left: 125px;
        bottom: -15px;
        display: none;
    }

    .error-input {
        border-color: #e53935 !important;
        background: #fff6f6;
    }
</style>


<div id="editModal" class="modal hidden">
    <div class="modal-content">
        <h3 id="modalTitle">Dynamic Heading</h3>
        <form method="post" id="editForm" class="modal-form">
            <input type="hidden" name="vendor_id" id="vendor_id">

            <div class="form-group">
                <label for="vendor_name">Vendor Name *</label>
                <input type="text" name="vendor_name" id="vendor_name">
                <div class="error-message" id="vendor_name_error">Vendor name is required.</div>
            </div>

            <div class="form-group">
                <label for="pan">PAN</label>
                <input type="text" name="pan" id="pan">
            </div>

            <div class="form-group">
                <label for="contact_person_name">Contact Person</label>
                <input type="text" name="contact_person_name" id="contact_person_name">
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" name="email" id="email">
                <div class="error-message" id="email_error">Enter a valid email.</div>
            </div>

            <div class="form-group">
                <label for="phone">Phone *</label>
                <input type="number" name="phone" id="phone">
                <div class="error-message" id="phone_error">Enter a valid phone number (digits only).</div>
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" name="address" id="address">
            </div>

            <div class="form-group">
                <label for="remarks">Remarks</label>
                <input type="text" name="remarks" id="remarks">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn" id="saveBtn">Save</button>
                <button type="button" class="btn btn-clear" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>