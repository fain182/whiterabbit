$(document).ready(function() {
    var tooltip = $('<div/>').qtip({
        id: 'calendar',
        content: {
            text: ' ',
            title: {
                button: true
            }
        },
        position: {
            my: 'bottom right',
            at: 'top center',
            target: 'event',
        },
        show: false,
        hide: false,
        style: 'qtip-blue'
    }).qtip('api');

    $('#calendar').fullCalendar({
        header: {
            right: 'next',
            center: 'title today',
            left: 'prev'
        },
        events: {
            url: '/time',
            data: function(){
                return {
                    user: $("#timesheet_users" ).val()
                }
            }
        },
        eventClick: function(data, event, view) {
            tooltip.set({
                'content.text': data.details,
                'style.classes': 'qtip-blue qtip-my ' + data.className[1]
            })
            .show(event);
         },
        dayClick: function() { tooltip.hide() },
        firstDay: 1,
        editable: true
    });

    $('#timesheet_users').change(function(e){
        var optionSelected = $(this).find("option:selected");
        var textSelected   = optionSelected.text();
        localStorage.setItem('whiterabbit_user_id', optionSelected.val());
        $('#calendar').fullCalendar('refetchEvents');

        $('.showmsg').text("Showing time for " + textSelected);
    });

    if (localStorage.getItem('whiterabbit_user_id')) {
        $("#timesheet_users" ).val(localStorage.getItem('whiterabbit_user_id'));
        $("#timesheet_users" ).trigger("change");
    }
});
