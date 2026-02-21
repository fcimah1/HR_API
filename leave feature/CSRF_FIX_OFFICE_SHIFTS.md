# CSRF Error Fix for Office Shifts

## Problem
When adding a new office shift, you get a 403 Forbidden error with the message:
```
The action you requested is not allowed.
```

This is a **CSRF (Cross-Site Request Forgery) token validation error**.

## Root Cause
The CSRF token in the form becomes invalid after the first submission because:
1. The form is reset with `$('#xin-form')[0].reset()`
2. The CSRF token was being updated **before** the reset
3. The reset clears the updated token
4. The second submission uses an invalid/empty token

## Solution Applied

### Fix 1: Enable CSRF Regeneration
**File Modified:** `app/Config/Security.php`
```php
public $regenerate = true; // Changed from false to true
```

### Fix 2: Update Token After Form Reset (CRITICAL FIX)
**File Modified:** `public/module_scripts/office_shift.js`

**Before (WRONG):**
```javascript
$('input[name="csrf_token"]').val(JSON.csrf_hash);  // Update token
$('.add-form').removeClass('show');
$('#xin-form')[0].reset();  // Reset clears the token!
```

**After (CORRECT):**
```javascript
$('.add-form').removeClass('show');
$('#xin-form')[0].reset();  // Reset form first
$('input[name="csrf_token"]').val(JSON.csrf_hash);  // Then update token
```

The token must be updated **AFTER** the form reset, not before.

## Testing the Fix

1. **Clear your browser cache** (Ctrl+Shift+Delete)
2. **Refresh the page** (Ctrl+F5)
3. **Add a new office shift** - Should work ✅
4. **Add another shift WITHOUT reloading the page** - Should also work ✅

Both submissions should now succeed!

## Why This Fix Works

The sequence of operations is critical:

**Wrong Order:**
1. Submit form → Success
2. Update CSRF token with new value
3. Reset form → **Clears all fields including the token we just updated**
4. Next submission → Uses empty/invalid token → 403 Error

**Correct Order:**
1. Submit form → Success
2. Reset form → Clears all fields
3. Update CSRF token with new value → **Token is now set correctly**
4. Next submission → Uses valid token → Success ✅

## Additional Troubleshooting

### If the error persists:

1. **Check if the CSRF token field exists in the form:**
   - Right-click on the page → Inspect Element
   - Find the form with id="xin-form"
   - Look for an input field with name="csrf_token"
   - It should look like: `<input type="hidden" name="csrf_token" value="...">`

2. **Check the AJAX request:**
   - Open Browser Developer Tools (F12)
   - Go to Network tab
   - Submit the form
   - Click on the "add_office_shift" request
   - Check the "Form Data" section
   - Verify that "csrf_token" is being sent

3. **Check the server response:**
   - In the same Network tab
   - Look at the Response
   - It should return JSON with either `result` or `error` field
   - If you see HTML instead, there's a server-side error

### If you see "csrf_token" is missing in Form Data:

The form might not be including the CSRF field. Add it manually:

**Edit:** `app/Views/erp/office_shift/staff_officeshifts.php`

Find the form opening tag (around line 77):
```php
<?php echo form_open('erp/officeshifts/add_office_shift', $attributes, $hidden); ?>
<?= csrf_field() ?>
```

Make sure `<?= csrf_field() ?>` is present right after `form_open()`.

### If the CSRF token is being sent but still getting 403:

The token might be invalid. Try this:

**Edit:** `app/Config/Security.php`

```php
// Increase the expiration time
public $expires = 14400; // 4 hours instead of 2

// Or disable CSRF for testing (NOT RECOMMENDED for production)
// In app/Config/Filters.php, comment out the CSRF filter temporarily
```

## Why This Happens

CSRF protection is a security feature that prevents malicious websites from submitting forms on behalf of your users. Each form must include a valid CSRF token that matches the token stored in the user's session/cookie.

The token can become invalid if:
- The session expires
- The browser cache is outdated
- The token regeneration is disabled and the token expires
- There's a mismatch between the form token and the cookie token

## Verification

After applying the fix, you should be able to:
1. ✅ Open the Office Shifts page
2. ✅ Click "Add New Office Shift"
3. ✅ Fill in the form
4. ✅ Submit successfully without 403 error
5. ✅ See the new shift in the list

## Related Files

- `app/Config/Security.php` - CSRF configuration
- `app/Views/erp/office_shift/staff_officeshifts.php` - Add form view
- `app/Controllers/Erp/Officeshifts.php` - Controller handling the request
- `public/module_scripts/office_shift.js` - JavaScript handling form submission

## Note

This CSRF error is **NOT related** to the leave hours calculation fix we just implemented. It's a separate issue with the office shifts module.

The leave hours calculation fix is working correctly and doesn't affect CSRF token handling.
