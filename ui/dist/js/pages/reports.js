/**
 * IOT PORTAL 1.0.0
 * ------------------
 * Description:
 *      This is a js file used only for the reporting cards.
 */

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
                'data': v, 'title': v, 'render': function (a, display, val, row, e, f) {
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
            "dom":
                "<'row'<'col-sm-12 col-md-6'<'text-gray font-weight-bold report-caption'>><'col-sm-12 col-md-6 text-right pt-5'lfB>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",//'Bfrtip'"
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
            buttons: [
                {
                    extend: 'collection',
                    text: 'Export',
                    autoClose: true,
                    buttons: [
                        {
                            text: 'CSV', action: function (e, dt, node, config) {
                                ExportReport('CSV')
                            }
                        },
                    ],
                    fade: true,
                }
            ],
            'columns': columns,
            'ajax': $.fn.dataTable.pipeline({
                url: '/api/reports.php',
                data: function (d) {
                    d.method = 'IOT_GetReport';
                    d.report = $('#sel_report').val();
                    d.customer = $('#sel_customer').val();
                    d.period = $('#sel_date').val();
                    d.nocache = 1;
                },
            }),
            'initComplete': function (settings, data) {
                $('.report-caption').html(reportName);
                $('#reports-loading').hide()
                $('#reports-body').show()
            },
            "language":
                {
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

var result_table = null;
var result_table_columns = [];


// Register an API method that will empty the pipelined data, forcing an Ajax
$.fn.dataTable.Api.register('clearPipeline()', function () {
    return this.iterator('table', function (settings) {
        settings.clearCache = true;
    });
});

$.fn.dataTable.pipeline = function (opts) {
    // Configuration options
    var conf = $.extend({
        pages: 10,     // number of pages to cache
        url: '',      // script url
        data: null,   // function or object with parameters to send to the server
        // matching how `ajax.data` works in DataTables
        method: 'GET' // Ajax HTTP method
    }, opts);

    // Private variables for storing the cache
    var cacheLower = -1;
    var cacheUpper = null;
    var cacheLastRequest = null;
    var cacheLastJson = null;

    return function (request, drawCallback, settings) {

        var ajax = false;
        var requestStart = request.start;
        var drawStart = request.start;
        var requestLength = request.length;
        var requestEnd = requestStart + requestLength;
        if (settings.clearCache) {
            // API requested that the cache be cleared
            ajax = true;
            settings.clearCache = false;
        } else if (cacheLower < 0 || requestStart < cacheLower || requestEnd > cacheUpper) {
            // outside cached data - need to make a request
            ajax = true;
        } else if (JSON.stringify(request.order) !== JSON.stringify(cacheLastRequest.order))
        {
            // properties changed (ordering, columns, searching)
            ajax = true;
        }

        // Store the request for checking next time around
        cacheLastRequest = $.extend(true, {}, request);

        if (ajax) {
            var ajaxData = {};

            // Need data from the server
            if (requestStart < cacheLower) {
                requestStart = requestStart - (requestLength * (conf.pages - 1));

                if (requestStart < 0) {
                    requestStart = 0;
                }
            }

            cacheLower = requestStart;
            cacheUpper = requestStart + (requestLength * conf.pages);

            //Request data
            ajaxData.sort = "`" + request.columns[request.order[0].column].data + "` " + request.order[0].dir;
            ajaxData.start = requestStart;
            ajaxData.length = requestLength * conf.pages;


            // Provide the same `data` options as DataTables.
            if (typeof conf.data === 'function') {
                var d = conf.data(ajaxData);
                if (d) {
                    $.extend(ajaxData, d);
                }
            } else if ($.isPlainObject(conf.data)) {
                $.extend(ajaxData, conf.data);
            }

            return $.ajax({
                "type": conf.method,
                "url": conf.url,
                "data": ajaxData,
                "dataType": "json",
                "cache": false,
                "success": function (json) {
                    if (json.message.Error != null) {
                        toastr.error(json.message.Error);
                        dt = {
                            "data": [],
                            "recordsTotal": 0,
                            "recordsFiltered": 0
                        }
                    } else if (json.message.toString().indexOf("FAIL") !== -1) {
                        toastr.error(json.message);
                        dt = {
                            "data": [],
                            "recordsTotal": 0,
                            "recordsFiltered": 0
                        }
                    } else {
                        dt = {
                            "data": json.message.result,
                            "recordsTotal": json.message.total,
                            "recordsFiltered": json.message.total
                        }
                    }

                    cacheLastJson = $.extend(true, {}, dt);

                    if (cacheLower != drawStart) {
                        dt.data.splice(0, drawStart - cacheLower);
                    }
                    if (requestLength >= -1) {
                        dt.data.splice(requestLength, dt.data.length);
                    }

                    drawCallback(dt);
                    if (json.message.summary) {
                        $(result_table.column(0).footer()).css("text-align", "right").html("Total");
                        $.each(json.message.summary, function (index, value) {
                            $(result_table.column(result_table_columns.indexOf(index)).footer()).html(parseFloat(value).toFixed(2));

                        });

                    }

                }
            });
        } else {
            dt = $.extend(true, {}, cacheLastJson);
            //dt.draw = request.draw;
            dt.data.splice(0, requestStart - cacheLower);
            dt.data.splice(requestLength, dt.data.length);

            drawCallback(dt);
        }
    }
};

var request_method_list = "";
var getFromAPI = function (dataparam, action, fail, is_async) {
    var request_method = dataparam.method;
    if (!request_method_list.includes(request_method)) {
        request_method_list += '<h5><strong>' + request_method + ' failed to load.</strong></h5>'
    }
    var maxtimeout = 180000;  //maxtimeout of 180 seconds (180 * 1000ms)
    var baseurl = "/api/reports.php";
    if (is_async == null) is_async = true;
    $.ajax({
        method: 'GET',
        type: 'JSON',
        contentType: 'application/json; charset=utf-8',
        url: baseurl,
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
                err = {message: [err]}
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
