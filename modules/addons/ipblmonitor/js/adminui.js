

$(document).ready(function() 
{

    $("#ipblmonitor_loader").mask("Loading.........");

    $(window).bind('resize', resizeGridPanel).trigger('resize');
	$("#maintabs").tabs({
        active:0,
        select: function(event, tab) /* <=1.8*/
        {
        	$(window).trigger('resize');
        },
        create:function(event, ui) 
        {
            $(window).trigger('resize');
        },
        activate: function(event, ui) /* >=1.9*/
        {
            switch(ui.newPanel[0].id){
                case "dashboard":
                {
                    drawChart();
                    break;
                }
                case "dnsbllistedip":{
                    $("#dnsbllistedipGrid").trigger( 'reloadGrid' );
                    break;
                }
                case "monitoredip":{
                    if($("#monitoredipGrid").isMasked() === false) {
                        $("#monitoredipGrid").trigger('reloadGrid');
                    }
                    break;
                }
                case "dnsblservers":{
                    $("#dnsblserversGrid").trigger( 'reloadGrid' );
                    break;
                }
                case "settings":{
                    loadSettings();
                    break;
                }
            }

        	$(window).trigger('resize');
        }
    });


    initDashBoardPanel();
    initBlackListedIpGrid();
    initMonitoredIpGrid();
    initBlCheckerGrid();
    initDnsBlServersGrid();
    initSettingPanel();

    $("#savesettings").click(saveSettings);

    ipBlMonitor();



});


function ipBlMonitor()
{
    ajaxCall('IpblMonitor',function (status, json) {
        switch (status) {
            case 'success': {

                if(json.recheckscan == 'runned'){
                    if(!$("#monitoredip").isMasked()){
                        $("#monitoredip").mask("Scan in progress.....");
                    }
                }
                else if(json.recheckscan == 'finished'){
                    if($("#monitoredip").isMasked()) {
                        $("#monitoredip").unmask();
                        $("#monitoredipGrid").trigger('reloadGrid');
                    }
                }

                break;
            }
            case 'error':
            case 'done': {
                setTimeout(ipBlMonitor,5000);
                break;
            }
        }
    });
}



function saveSettings()
{
    $("#settings").mask("Processing....");
    var params = Array();
    params.notifyemails = $("#notifyemails").val();
    params.checkfrequency = $("#checkfrequency").val();
    params.licensekey = $("#licensekey").val();
    params.smtp_host = $("#smtp_host").val();
    params.smtp_port = $("#smtp_port").val();
    params.smtp_user = $("#smtp_user").val();
    params.smtp_password = $("#smtp_password").val();
    params.adminusername = $("#adminusername").val();
    params.enable_notifyemails=$("#enable_notifyemails").bootstrapSwitch('state')===true?'yes':'no';

    ajaxCall('saveSettings',function (status, json) {
        switch (status) {
            case 'success': {
            }
            case 'error':
            case 'done': {
                $("#settings").unmask();
                break;
            }
        }
    },params,"POST");
}

function loadSettings()
{
    $("#settings").mask("Loading....");
    ajaxCall('getSettings',function(status,json){
        switch(status) {
            case 'success': {

                if(json == null){return;}
                if(json.croncmd) {
                    $("#croncmd").val(json.croncmd);
                }
                if(json.licensekey) {
                    $("#licensekey").val(json.licensekey);
                }
                if(json.checkfrequency){
                    $("#checkfrequency").val(json.checkfrequency);
                }
                if(json.notifyemails){
                    $("#notifyemails").val(json.notifyemails);
                }
                if(json.smtp_host){
                    $("#smtp_host").val(json.smtp_host);
                }
                if(json.smtp_port){
                    $("#smtp_port").val(json.smtp_port);
                }
                if(json.smtp_user){
                    $("#smtp_user").val(json.smtp_user);
                }
                if(json.smtp_password){
                    $("#smtp_password").val(json.smtp_password);
                }


                if(json.adminusername){
                    $("#adminusername").val(json.adminusername);
                }


                $("#enable_notifyemails").bootstrapSwitch({state: (json.enable_notifyemails==='yes'?true:false)});


            }
            case 'error':
            case 'done': {
                $("#settings").unmask();
                break;
            }
        }
    });

}


function drawChart()
{

    var params = {};
    ajaxCall('dashStats', function (status, json) {
        switch (status) {
            case 'success': {
                if (json == null || json.rbltotal == undefined) {
                    return;
                }

                var options = {
                    pieSliceText: 'value-and-percentage',
                    sliceVisibilityThreshold: 0.000000001,
                    tooltip: {
                        showColorCode: true,
                        text: 'value',
                        trigger: 'selection'
                    },
                    legend: {
                        position: 'labeled',
                        labeledValueText: 'both',
                        textStyle: {

                            fontSize: 14
                        }
                    },
                    pieSliceTextStyle: {
                        fontSize: 8
                    },
                };

                var dataArr = Object.keys(json.rbltotal.data).map(function (key) {
                     return [key,json.rbltotal.data[key]];
                });

                var data = google.visualization.arrayToDataTable(dataArr);


                    options.title= json.rbltotal.title;


                var chart = new google.visualization.PieChart(document.getElementById('piechart'));

                chart.draw(data, options);


                var dataArr = Object.keys(json.toprbl.data).map(function (key) {
                    return [key,json.toprbl.data[key]];
                });

                var data = google.visualization.arrayToDataTable(dataArr);


                var top_options = {
                    pieSliceText: 'value',
                    title : json.toprbl.title
            }

                var chart = new google.visualization.PieChart(document.getElementById('toprblchart'));

                chart.draw(data, top_options);


                var dataArr = Object.keys(json.ipinfo.data).map(function (key) {
                    return [key,json.ipinfo.data[key]];
                });

                var data = google.visualization.arrayToDataTable(dataArr);


                var options = {
                    title:json.ipinfo.title,
                    pieSliceText: 'value-and-percentage',
                    sliceVisibilityThreshold: 0.000000001,
                    tooltip: {
                        showColorCode: true,
                        text: 'value',
                        trigger: 'selection'
                    },
                    legend: {
                        position: 'labeled',
                        labeledValueText: 'both',
                        textStyle: {

                            fontSize: 14
                        }
                    },
                    pieSliceTextStyle: {
                        fontSize: 8
                    },
                };


                var chart = new google.visualization.PieChart(document.getElementById('ipinfochart'));

                chart.draw(data, options);




            }
            case 'error':
            case 'done': {

                break;
            }
        }
    },params);


    if($("#ipblmonitor_loader").isMasked()){
        $("#ipblmonitor_loader").unmask();
        $("#ipblmonitor_loader").hide();
        $("#ipblmonitor_ctrl_panel").toggle(1000);

    }
}


function initDashBoardPanel()
{
    google.charts.load('current', {'packages':['corechart']});
    google.charts.setOnLoadCallback(drawChart);


}


function initBlCheckerGrid()
{

    $("#blcheckerGrid").jqGrid({
        datatype: "local",
        height: 'auto',
        autowidth: true, shrinkToFit: true,
        pager: $("#blcheckergridpager"),
        rowNum: 200,
        rowList: [10, 20, 30, 50, 100, 500],
        viewrecords: true,
        toppager: true,
        sortorder: "desc",
        sortname: "ip",
        jsonReader: jSonReaderOptions,
        multiselect: false,
        beforeProcessing: gridBeforeProcessing,
        subGrid: true,
        colNames: ['','id', 'IP/Domain', 'Host Name', 'rDNS', 'Blacklisted'],
        colModel: [
            {name: 'jid',hidden: true},
            {name: 'id', index: 'id', hidden: true},
            {name: 'ip', jsonmap: 'ip', index: 'ip', editable: false, width:150},
            {name: 'host_name', editable: false, jsonmap: 'host_name', index: 'host_name'},
            {name: 'rdns', editable: false, jsonmap: 'rdns', index: 'rdns'},
            {name: 'blacklisted', editable: false, jsonmap: 'blacklisted', width:50,
                index: 'blacklisted',align:"center"}
        ],
        subGridRowExpanded: function(subgrid_id, row_id)
        {
            var parentGrid = subgrid_id.split("-")[0]+"-tsub";
            var subgrid_table_id, pager_id;
            subgrid_table_id = subgrid_id+"-tsub";
            pager_id = "p_"+subgrid_table_id;
            $("#"+subgrid_id).html("<table width='100%' id='"+subgrid_table_id+"' class='scroll'></table><div id='"+pager_id+"' class='scroll'></div>");

            var ip = $("#blcheckerGrid").jqGrid ('getCell', row_id, 'ip');
            var jid = $("#blcheckerGrid").jqGrid ('getCell', row_id, 'jid');
            jQuery("#"+subgrid_table_id).jqGrid({
                url: 'addonmodules.php?module=ipblmonitor&f=rblCheckGetResultRbls&ip=' + ip+'&jid='+jid,
                pager: pager_id,
                subGrid: false,
                sortname: 'rbl_server',
                multiselect: false,
                colNames: ['RBL Server', 'Response A','Response TXT'],
                colModel: [
                    {name: 'rbl_server', index: 'rbl_server', editable: true,search: true, align: "center"},
                    {name: 'rbl_response_a', index: 'rbl_response_a', editable: true, width: 40, search: false, align: "center"},
                    {name: 'rbl_response_txt', index: 'rbl_response_txt', editable: true,search: true, align: "center"}
                ]
            }).navGrid("#"+pager_id,
                {cloneToTop:true,edit:false,add:false,del:false,search:false}

            );

            $(window).trigger('resize');
        }
    }).navGrid("#blcheckergridpager",
        {cloneToTop:true,edit:false,add:false,del:false,search:false});


    $("#btn_blcheckip").on("click",function() {

        var blcheckip = $("#blcheckip").val();

        if (blcheckip === "") {
            BootstrapDialog.alert("Enter IP Address or Domain");
            return;
        }

        $("#blchecker").mask("Checking....");
        $("#blcheckerGrid").jqGrid("clearGridData");
        var params = Array();
        params.blcheckip = blcheckip;
        ajaxCall('rblCheck', function (status, json) {
            switch (status) {
                case 'success': {
                    if (json == null) {
                        return;
                    }

                    var updateCheckFunction = function(){
                        var params = Array();
                        params.jid = json.jid;
                        ajaxCall('rblCheckGetResult', function (status, json) {
                            switch (status) {
                                case 'success': {
                                    if (json == null) {
                                        return;
                                    }

                                    $("#blackrbls").html(json.blackrbls);
                                    $("#totrbls").html(json.totrbls);

                                    $("#blcheckerGrid").jqGrid('setGridParam', {data: json.rows}).trigger('reloadGrid');


                                    if(json.status == "finished"){
                                        $("#blchecker").unmask();
                                        return;
                                    }
                                    setTimeout(updateCheckFunction,1000);
                                }
                                case 'error':
                                case 'done': {

                                    break;
                                }
                            }
                        },params);
                    };

                    setTimeout(updateCheckFunction,1000);
                }
                case 'error':
                case 'done': {

                    break;
                }
            }
        },params);
    });
}



function initBlackListedIpGrid()
{
    var dnsbllistedipGrid=$("#dnsbllistedipGrid"),inEdit;
    dnsbllistedipGrid.jqGrid({
        url: "addonmodules.php?module=ipblmonitor&f=getBlackListedGroups",
        datatype: "json",
        height: 'auto',
        autowidth:true,shrinkToFit:true,
        pager:$("#dnsbllistedipgridpager"),
        rowNum:20,
        rowList:[10,20,30,50,100,500],
        viewrecords: true,toppager:true,
        sortorder: "desc",
        sortname: "name",
        jsonReader: jSonReaderOptions,
        multiselect: false,
        beforeProcessing:gridBeforeProcessing,
        subGrid: true,
        colNames:['id','Group Name','Total IP\'s','BlackListed'],
        colModel:[
            {name:'id',index:'id',hidden:true},
            {name:'name',jsonmap:'name',index:'name', editable:true},
            {name:'totalip',editable:false,jsonmap:'totalip',index:'totalip',align:'center'},
            {name:'blacklisted',editable:false,jsonmap:'blacklisted',index:'blacklisted',width: 40,align:'center'}
        ],
        subGridRowExpanded: function(subgrid_id, row_id)
        {
            var subgrid_table_id, pager_id;
            subgrid_table_id = subgrid_id+"-tsub";

            pager_id = "p_"+subgrid_table_id;
            $("#"+subgrid_id).html("<table width='100%' id='"+subgrid_table_id+"' class='scroll'></table><div id='"+pager_id+"' class='scroll'></div>");
            var gid = $("#dnsbllistedipGrid").jqGrid ('getCell', row_id, 'id');
            jQuery("#"+subgrid_table_id).jqGrid({
                url: 'addonmodules.php?module=ipblmonitor&f=getBlackListedIps&gid=' + gid,
                pager: pager_id,
                subGrid: true,

                onSelectRow: function (rowId) {
                    $("#" + subgrid_table_id).jqGrid('toggleSubGridRow', rowId);
                },
                sortname: 'ip',
                ajaxRowOptions: {
                    success: function (response) {

                        return true;
                    }
                },
                multiselect: false,
                colNames: ['', 'IP', 'HostName', 'rDNS','LastScan'],
                colModel: [
                    {name: 'id', index: 'id', hidden: true},
                    {name: 'ip', index: 'ip', editable: true,search: true, align: "center"},
                    {name: 'host_name', index: 'host_name', editable: true, width: 40, search: false, align: "center"},
                    {name: 'rdns', index: 'rdns', editable: true,search: true, align: "center"},
                    {name: 'lastcheck', index: 'lastcheck', align: "center", editable: false, search: false, width: 100},

                ],
                subGridRowExpanded: function(subgrid_id, row_id)
                {
                    var parentGrid = subgrid_id.split("-")[0]+"-tsub";
                    var subgrid_table_id, pager_id;
                    subgrid_table_id = subgrid_id+"-tsub";
                    pager_id = "p_"+subgrid_table_id;
                    $("#"+subgrid_id).html("<table width='100%' id='"+subgrid_table_id+"' class='scroll'></table><div id='"+pager_id+"' class='scroll'></div>");
                    var ip = $("#"+parentGrid).jqGrid ('getCell', row_id, 'ip');
                    jQuery("#"+subgrid_table_id).jqGrid({
                        url: 'addonmodules.php?module=ipblmonitor&f=getBlackListedRbl&ip=' + ip,
                        pager: pager_id,
                        subGrid: false,

                        onSelectRow: function (rowId) {
                            $("#" + subgrid_table_id).jqGrid('toggleSubGridRow', rowId);
                        },
                        sortname: 'rbl_server',
                        ajaxRowOptions: {
                            success: function (response) {

                                return true;
                            }
                        },
                        multiselect: false,
                        colNames: ['RBL Server', 'Response A','Response TXT'],
                        colModel: [
                            {name: 'rbl_server', index: 'rbl_server', editable: true,search: true, align: "center"},
                            {name: 'rbl_response_a', index: 'rbl_response_a', editable: true, width: 40, search: false, align: "center"},
                            {name: 'rbl_response_txt', index: 'rbl_response_txt', editable: true,search: true, align: "center"},

                        ]
                    }).navGrid("#"+pager_id,
                        {cloneToTop:true,edit:false,add:false,del:false,search:false}

                    );

                    $(window).trigger('resize');
                },



            }).navGrid("#"+pager_id,
                {cloneToTop:true,edit:false,add:false,del:false,search:false}

            );

            $(window).trigger('resize');
        },


    }).navGrid('#dnsbllistedipgridpager',
        {cloneToTop:true,search:false,edit:false,add:false,del:false
        });

}

function initMonitoredIpGrid()
{

    var forceRecheckBtn = {
        caption:"", title:"Force ReCheck Monitored Groups",
        buttonicon:"ui-icon-shuffle",
        onClickButton: function()
        {
            BootstrapDialog.confirm("Start scan for all monitored groups?",function(result) {
                if(result) {
                    $("#monitoredip").mask("Scan in progress.....");
                    ajaxCall('forceReCheckMonitorIps', function (status, json) {
                        switch (status) {
                            case 'success': {
                            }
                            case 'error':
                            case 'done': {

                                break;
                            }
                        }
                    });
                }
            });
        }
    };


    var monitoredipGrid=$("#monitoredipGrid"),inEdit;
    monitoredipGrid.jqGrid({
        url: "addonmodules.php?module=ipblmonitor&f=getIpGroups",
        editurl: "addonmodules.php?module=ipblmonitor&f=setIpGroups",
        datatype: "json",
        height: 'auto',
        autowidth:true,shrinkToFit:true,
        pager:$("#monitoredipgridpager"),
        rowNum:20,
        rowList:[10,20,30,50,100,500],
        viewrecords: true,toppager:true,
        sortorder: "desc",
        sortname: "name",
        jsonReader: jSonReaderOptions,
        multiselect: false,
        beforeProcessing:gridBeforeProcessing,
        subGrid: true,
        colNames:['id','Group Name','Total IP\'s','BlackListed'],
        colModel:[
            {name:'id',index:'id',hidden:true},
            {name:'name',jsonmap:'name',index:'name', editable:true},
            {name:'totalip',editable:false,jsonmap:'totalip',index:'totalip',align:'center'},
            {name:'blacklisted',editable:false,jsonmap:'blacklisted',index:'blacklisted',width: 40,align:'center'}



        ],
        subGridRowExpanded: function(subgrid_id, row_id)
        {
            var subgrid_table_id, pager_id;
            subgrid_table_id = subgrid_id+"-tsub";
            pager_id = "p_"+subgrid_table_id;
            $("#"+subgrid_id).html("<table width='100%' id='"+subgrid_table_id+"' class='scroll'></table><div id='"+pager_id+"' class='scroll'></div>");
            var gid = $("#monitoredipGrid").jqGrid ('getCell', row_id, 'id');
            jQuery("#"+subgrid_table_id).jqGrid({
                url: 'addonmodules.php?module=ipblmonitor&f=getMonitoredIps&gid=' + gid,
                editurl: 'addonmodules.php?module=ipblmonitor&f=setMonitoredIps&gid=' + gid,
                pager: pager_id,
                subGrid: false,

                onSelectRow: function (rowId) {
                    $("#" + subgrid_table_id).jqGrid('toggleSubGridRow', rowId);
                },
                sortname: 'ip_start',
                ajaxRowOptions: {
                    success: function (response) {

                        return true;
                    }
                },
                multiselect: false,
                colNames: ['', 'StartIP/Domain', 'EndIP', 'Netmask','Total IP\'s','BlackListed','LastScan'],
                colModel: [
                    {name: 'id', index: 'id', hidden: true},
                    {name: 'ip_start', index: 'ip_start', editable: true,search: true, align: "center"},
                    {name: 'ip_end', index: 'ip_end', editable: true, width: 40, search: false, align: "center"},
                    {name: 'netmask', index: 'netmask', editable: true,search: true, align: "center"},
                    {name:'totalip',editable:false,jsonmap:'totalip',index:'totalip',align:'center'},
                    {name:'blacklisted',editable:false,jsonmap:'blacklisted',index:'blacklisted',align:'center'},
                    {name: 'lastscan', index: 'lastscan', align: "center", editable: false, search: false, width: 100},

                ]
            }).navGrid("#"+pager_id,
                {cloneToTop:true,edit:true,add:true,del:true,search:false}

            ).filterToolbar();

            $(window).trigger('resize');
        },


    }).navGrid('#monitoredipgridpager',
        {cloneToTop:true,search:false,edit:true,add:true,del:true,
            addtitle:"Add new group",
            deltitle:"Delete selected group"
        }).navButtonAdd('#monitoredipgridpager',forceRecheckBtn)
                .navButtonAdd('#monitoredipGrid_toppager_left',forceRecheckBtn );


}


function initDnsBlServersGrid()
{
    var dnsblserversGrid=$("#dnsblserversGrid"),inEdit;
    dnsblserversGrid.jqGrid({
        url: "addonmodules.php?module=ipblmonitor&f=getDnsblServers",
        editurl: "addonmodules.php?module=ipblmonitor&f=editDnsblServer",
        datatype: "json",
        height: 'auto',
        autowidth:true,shrinkToFit:true,
        pager:$("#dnsblserversgridpager"),
        rowNum:20,
        rowList:[10,20,30],
        viewrecords: true,toppager:true,
        sortorder: "desc",
        sortname: "name",
        jsonReader: jSonReaderOptions,
        multiselect: true,
        beforeProcessing:gridBeforeProcessing,
        loadComplete:function(){
            $("td[aria-describedby$=_enabled]").each(function(){
                var rowID = $(this).parent().attr('id');

                var inputEl=$(this).find('input[type=checkbox]');
                inputEl.bootstrapSwitch({state: inputEl.prop( "checked" )});
            });

            $("td[aria-describedby$=_enabled]").on('switchChange.bootstrapSwitch', function(event, state) {
                    var rowID = $(this).parent().attr('id');
                    var params = {};
                    params.dnszone=$("#dnsblserversGrid").jqGrid ('getCell', rowID, 'dnszone');
                    params.enabled = state?'yes':'no';
                    params.oper='edit';
                    ajaxCall('editDnsblServer',null,params);
             });
        },
        colNames:[
            'IPv4','IPv6','Domain','Url','DNS Zone','Name','Description','Enabled','Status','Last Check'],
        colModel:[
            {name:'ipv4',jsonmap:'ipv4',index:'ipv4', hidden:true,editable:true,
                editrules: {edithidden:true},formatter: "checkbox",
                edittype:'checkbox',editoptions: { value:"yes:no"}},
            {name:'ipv6',jsonmap:'ipv6',index:'ipv6', hidden:true,editable:true,
                editrules: {edithidden:true},formatter: "checkbox",
                edittype:'checkbox',editoptions: { value:"yes:no"}},
            {name:'domain',jsonmap:'domain',index:'domain', hidden:true,editable:true,
                editrules: {edithidden:true},formatter: "checkbox",
                edittype:'checkbox',editoptions: { value:"yes:no"}},
            {name:'url',jsonmap:'url',index:'url', hidden:true,editable:true,editrules: {edithidden:true}},
            {name:'dnszone',jsonmap:'dnszone',index:'dnszone', editable:true,width:50,
                editrules:{required:true},align:'center',
                formatter:function(cellvalue, options, rowObject){

                    if(rowObject.url){
                        return '<a style="color:blue;" target="blank" href="'+rowObject.url+'">'+cellvalue+'</a>';
                    }
                    return cellvalue;
                },
                unformat:function(cellvalue, options, cell){

                    return cellvalue;
                }
            },
            {name:'name',jsonmap:'name',index:'name',width:100,
                editable:true,align:'center'},
            {name:'description',jsonmap:'description',index:'description',width:70,
                editable:true,align:'center',edittype:'textarea'},
            {name:'enabled',jsonmap:'enabled',index:'enabled',width:30, editable:false,
                editrules:{required:true},align:'center',formatter: "checkbox",
                formatoptions: {disabled : false},
                edittype:'checkbox',editoptions: { value:"yes:no"}},
            {name:'status',jsonmap:'status',index:'status', editable:false,align:'center',width:15,
                formatter:function(cellvalue, options, rowObject){
                    if(cellvalue == "good") {
                        return '<div class="status_good align_center" />';
                    }
                    else {
                        return '<div class="status_bad align_center"/>';
                    }

            }},
            {name:'lastcheck',jsonmap:'lastcheck',index:'lastcheck', editable:false,align:'center',width:25}

        ]}).navGrid('#dnsblserversgridpager',
        {cloneToTop:true,search:false,edit:true,add:true,del:true,
            addtitle:"Add new server",
            deltitle:"Delete selected server",
        },
        {},
        {recreateForm:true,
            afterSubmit:gridPagerAfterSubmit,
            reloadAfterSubmit:true,
            closeAfterAdd: true,
            modal:true,height:'auto',width:'auto',
            addCaption:'Add new server',

        },
        {afterSubmit:gridPagerAfterSubmit,reloadAfterSubmit:true,
            serializeDelData: function (postdata) {
                var rowdata = jQuery('#dnsblserversGrid').getRowData(postdata.id);

                return {oper: postdata.oper, name: rowdata.dnszone};
            }
        });

}


function initSettingPanel()
{
    loadSettings();

}
