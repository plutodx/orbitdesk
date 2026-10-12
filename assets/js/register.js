/* =====================================================================
   ORBITDESK - assets/js/register.js
   Public signup for virtual assistants and clients. Both forms post to the
   same endpoint with a different role, and neither one reloads the page.
   ===================================================================== */

$(function () {

    const $alert = $('#registerAlert');
    let role = 'va';

    function show(message, isError) {
        $alert.removeClass('d-none ok').text(message);
        if (!isError) { $alert.addClass('ok'); }
    }

    $('#roleTabs').on('click', '.role-tab', function () {
        $('.role-tab').removeClass('active');
        $(this).addClass('active');

        role = $(this).data('role');

        $('.signup-form').addClass('d-none');
        $(role === 'va' ? '#vaSignupForm' : '#clientSignupForm').removeClass('d-none');
        $alert.addClass('d-none');
    });

    function submit($form) {
        const data = $form.serializeArray();
        data.push({ name: 'action', value: 'register' });
        data.push({ name: 'role',   value: role });

        const $btn = $form.find('button[type="submit"] .btn-label');
        const original = $btn.text();
        $btn.text('Creating...');

        $.ajax({
            url: 'db/request.php',
            type: 'POST',
            dataType: 'json',
            data: data,

            success: function (res) {
                $btn.text(original);

                if (res.status !== 'success') {
                    show(res.message || 'Could not create the account.', true);
                    return;
                }

                show(res.message, false);
                $form[0].reset();
                setTimeout(function () { window.location.href = 'login.php'; }, 2200);
            },

            error: function () {
                $btn.text(original);
                show('Could not reach the server. Check that Apache and MySQL are running.', true);
            }
        });
    }

    $('#vaSignupForm').on('submit', function (e) {
        e.preventDefault();
        submit($(this));
    });

    $('#clientSignupForm').on('submit', function (e) {
        e.preventDefault();
        submit($(this));
    });

});
