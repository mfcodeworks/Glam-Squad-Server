$(document).ready(function() {
    $("#new-password-form").submit(function(e) {
        e.preventDefault();

        // Validate password
        if(validatePassword() === false) {
            $("#new-password").addClass("is-invalid");
            alert("Password must be at least 4 characters long");
            return;
        }

        // Begin loading screen
        startLoader();

        // Build form
        var form = {
            key: $("#btn-new-password").data("key"),
            id: $("#btn-new-password").data("id"),
            password: $("#new-password").val()
        }

        var type = $("#btn-new-password").data("type");

        // Send new data
        apiSend("PUT", `https://glam-squad-db.nygmarosebeauty.com/api/v1/${type}s/${form.id}/forgot-password`, form)
            .then(function(r) {
                // End loader
                endLoader();

                // Log response
                console.log("User update response:\n" + JSON.stringify(r,null,2));
                
                // Alert response
                if(r.response === true) alert("Password changed successfully");
                else alert("An error occured, please try again later.\n"+JSON.stringify(r.error));

            // Catch error
            }, function(err) {
                console.warn(err);
                endLoader();
            });
    });

    // Send to API
    function apiSend(method = "GET", url, form = null) {
        var message = JSON.stringify(form);
    
        return new Promise(function(resolve, reject) {
            $.ajax({
                method: method,
                url: url,
                data: message,
                contentType: "application/json; charset=utf-8",
                dataType: "json",
                // timeout 60 seconds in milliseconds
                timeout: (60 * 1000),
                success: function(res) {
                    resolve(res);
                },
                error: function(xhr,status,err) {
                    reject(err);
                }
            });
        });
    }
    // Validate password
    function validatePassword() {
        var password = $("#new-password").val();

        if(password.length < 4) return false;
        return true;
    }
    
    // Loading UI tools
    function startLoader(color = "black") {
        $("body").prepend(`<div class='loader-${color}'></div>`);
    }
    function endLoader() {
        $("[class^='loader-']").remove();
    }
});