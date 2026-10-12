/* =====================================================================
   ORBITDESK - assets/js/login.js
   Handles the sign-in form for all three account types. Submits through
   AJAX so a failed attempt never reloads the page.
   ===================================================================== */

$(function () {

    const $form  = $('#loginForm');
    const $alert = $('#loginAlert');
    const $btn   = $('#loginBtn');

    // The public page uses tabs. The staff page hardcodes the role instead,
    // so this one script serves both without duplicating any logic.
    const fixedRole = $('#fixedRole').val();
    let role = fixedRole ? fixedRole : 'va';

    function showError(message) {
        $alert.removeClass('d-none ok').text(message);
    }

    function showSuccess(message) {
        $alert.removeClass('d-none').addClass('ok').text(message);
    }

    // Switching tabs swaps both the role and the demo account list.
    $('#roleTabs').on('click', '.role-tab', function () {
        $('.role-tab').removeClass('active');
        $(this).addClass('active');

        role = $(this).data('role');

        $('.demo-set').addClass('d-none').filter('[data-for="' + role + '"]').removeClass('d-none');
        $alert.addClass('d-none');
        $form[0].reset();
    });

    // Tapping a demo row fills the form so the checker can get in fast.
    $(document).on('click', '.demo-row', function () {
        $('#loginEmail').val($(this).data('email'));
        $('#loginPassword').val($(this).data('password'));
        $alert.addClass('d-none');
        $('#loginPassword').focus();
    });

    $form.on('submit', function (e) {
        e.preventDefault();

        const email    = $.trim($('#loginEmail').val());
        const password = $('#loginPassword').val();

        if (email === '' || password === '') {
            showError('Enter both your email and password.');
            return;
        }

        $btn.prop('disabled', true).find('.btn-label').text('Checking...');

        $.ajax({
            url: 'db/request.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'login', role: role, email: email, password: password },

            success: function (res) {
                if (res.status === 'success') {
                    showSuccess('Signed in. Opening your dashboard...');
                    window.location.href = res.redirect;
                    return;
                }
                showError(res.message || 'Sign in failed.');
                $btn.prop('disabled', false).find('.btn-label').text('Sign in');
            },

            error: function () {
                showError('Could not reach the server. Check that Apache and MySQL are running in MAMP.');
                $btn.prop('disabled', false).find('.btn-label').text('Sign in');
            }
        });
    });

});
