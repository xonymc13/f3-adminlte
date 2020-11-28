/**
 * IOT PORTAL 1.0.0
 * ------------------
 * Description:
 *      This is a js file used only for the reporting cards.
 */

var result_table = null;
var result_table_columns = [];

function GetCustomersList() {
    $('#admin_customer').show()
    getFromAPI({
        method: 'IOT_GetCustomerList'
    }, function (data) {
        if (data.message != null) {
            $('#sel_customer')
                .find('option')
                .remove()
                .end()
            $('#sel_customer')
                .append('<option value="">Select One</option>')
            for (i = 0; i < data.message.length; i++) {
                var value = data.message[i]['customer']
                var text = data.message[i]['customer']
                if (value != null) {
                    selected = ''
                    $('#sel_customer')
                        .append('<option value="' + value + '" ' + selected + '>' + text + '</option>')
                }
            }
        }
    })
}

function GetPeriodList() {
    getFromAPI({
        method: 'IOT_GetUsagePeriodList'
    }, function (data) {
        if (data.message != null) {
            $('#sel_date')
                .find('option')
                .remove()
                .end()
            $('#sel_date')
                .append('<option value="">Select One</option>')
            for (i = 0; i < data.message.length; i++) {
                var value = data.message[i]['period']
                var text = data.message[i]['period']
                if (value != null) {
                    $('#sel_date')
                        .append($('<option>', {
                            value: value,
                            text: text
                        }))
                }
            }
        }
    })
}

function GetReportsList() {
    $('#sel_report')
        .find('option')
        .remove()
        .end()
    $('#sel_report')
        .append('<option value="">Select One</option>')
    getFromAPI({
        method: 'IOT_GetReportsList',
    }, function (data) {
        if (data.message != null) {
            for (i = 0; i < data.message.length; i++) {
                var value = data.message[i]
                var text = data.message[i]
                if (value != null) {
                    $('#sel_report')
                        .append($('<option>', {
                            value: value,
                            text: text
                        }))
                }
            }
        }
    })
}


function ExportReport(fileFormat) {
    //do something
    ShowExportReportDialog(fileFormat, $('#sel_report').val(), $('#sel_customer').val(), $('#sel_date').val())

}

function GetReport() {

    isError = false
    if ($('#sel_report').val() == '') {
        toastr.error('Error - Please select a valid report.');
        isError = true
    }
    if ($('#sel_date').val() == '') {
        toastr.error('Error - Please select a valid reporting period.');
        isError = true
    }
    if ($('#sel_customer').val() == '') {
        toastr.error('Error - Please select a valid customer');
        isError = true
    }
    if (isError) return

    if (result_table != null) {
        //reset table
        result_table.destroy();
        result_table = null;
        $('#report_list tbody').html('');
        $('#report_list thead').html('');
        $('#report_list tfoot').html('');
    }

    //show loading
    $('#reports-loading').show()
    $('#reports-body').hide()
    var columns = [];
    getFromAPI({
        method: 'IOT_GetReportColumns',
        report: $('#sel_report').val(),
    }, function (data) {
        result_table_columns = data.message;
        columns = $.map(data.message, function (v, i) {           
            return {
                'data': v,
                'title': v,
                'render': function (a, display, val, row, e, f) {
                    return val[v];
                }
            }
        });
    }, null, false);

    //set the report header.
    var reportName = 'Report: ' + $('#sel_report').val() + ' <br> Customer: ' + $('#sel_customer').val() + ' <br> Period: ' + $('#sel_date').val();

    //set footer
    var str = '<tr class="text-bold">';
    for (var k in columns) {
        str += '<td></td>'
    }
    str += '</tr>';
    $('#report_list tfoot').html(str);

    result_table = $('#report_list')
        .DataTable({
            "dom": "<'row'<'col-sm-12 col-md-6'<'text-gray font-weight-bold report-caption'>><'col-sm-12 col-md-6 text-right pt-5'lfB>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>", //'Bfrtip'"
            "processing": true,
            "serverSide": true,
            'paging': true,
            'lengthChange': false,
            'searching': false,
            'ordering': true,
            'info': true,
            'autoWidth': false,
            'responsive': true,
            'pageLength': 10,
            buttons: [{
                extend: 'collection',
                text: 'Export',
                autoClose: true,
                buttons: [{
                    text: 'CSV',
                    action: function (e, dt, node, config) {
                        ExportReport('CSV')
                    }
                }, ],
                fade: true,
            }],
            'columns': columns,
            'ajax': $.fn.dataTable.pipeline({
                url: '/f3-adminlte/api/reports.php',
                dataSrc: function (d) {
                    d.method = 'IOT_GetReport';
                    d.report = $('#sel_report').val();
                    d.customer = $('#sel_customer').val();
                    d.period = $('#sel_date').val();
                    d.nocache = 1;
                    return d;
                },
            }),
            'initComplete': function (settings, data) {
                $('.report-caption').html(reportName);
                $('#reports-loading').hide()
                $('#reports-body').show()
            },
            "language": {
                "processing": '<div class="spinner-border" role="status"> <span class="sr-only">Loading...</span> </div>' //"<i class='fa fa-refresh fa-spin'></i>",
            }
        });
}

function ShowOrdersReport() {

    //show loading
    $('#reports-loading').show()
    $('#reports-body').hide()

    var current_date = new Date()
    var cyear = current_date.getFullYear()
    var shortMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    var short_month = shortMonths[current_date.getMonth()];
    var period = short_month.toUpperCase() + ' ' + current_date.getFullYear()

    $('#sel_report').val('Order List')
    $('#sel_date').val(period)

    GetReport()

    //hide loading
    $('#reports-loading').hide()
    $('#reports-body').show()

}

var request_method_list = "";
var getFromAPI = function (dataparam, action, fail, is_async) {
    var request_method = dataparam.method;
    if (!request_method_list.includes(request_method)) {
        request_method_list += '<h5><strong>' + request_method + ' failed to load.</strong></h5>'
    }
    var maxtimeout = 180000; //maxtimeout of 180 seconds (180 * 1000ms)
    var baseurl = window.location.origin;
    if (is_async == null) is_async = true;
    $.ajax({
        method: 'GET',
        type: 'JSON',
        contentType: 'application/json; charset=utf-8',
        url: baseurl.concat("/f3-adminlte/api/reports.php"),
        async: is_async,
        data: dataparam,
        dataType: 'JSON',
        //timeout: maxtimeout,
        success: function (data) {
            data = eval(data);
            if (action) action(data)
        },
        error: function (m) {
            var err = (m.responseJSON != null ? m.responseJSON : {
                status: false,
                message: [m.responseText]
            });
            if (!err.message) {
                err = {
                    message: [err]
                }
            }
            console.log(err);
        }
    })
};

if (typeof jQuery === "undefined") {
    alert("AdminLTE requires jQuery");
}

//load this at the start of each page.
$(document).ready(function () {

    //show loading status
    $('#reports-loading').show()
    $('#reports-body').hide()

    //load dropdowns
    GetReportsList();
    GetPeriodList();
    GetCustomersList();

    //hide loading status
    $('#reports-loading').hide()
    $('#reports-body').show()

})

