
{if $adminhead}
{literal}   

<!-- Latest compiled and minified CSS -->
<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
<!-- Optional theme -->
<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap-theme.min.css">


<link type="text/css" href="../modules/addons/ipblmonitor/3rdlib/js/bootstrap3-dialog/css/bootstrap-dialog.min.css" rel="stylesheet">
<script type="text/javascript" src="../modules/addons/ipblmonitor/js/3rdlib/bootstrap3-dialog/js/bootstrap-dialog.min.js"></script>


    <!-- jqGrid -->
<link rel="stylesheet" href="../modules/addons/ipblmonitor/js/3rdlib/jqGrid/css/ui.jqgrid.css"  type="text/css"/>
<script src="../modules/addons/ipblmonitor/js/3rdlib/jqGrid/plugins/ui.multiselect.js" type="text/javascript"></script>
<script src="../modules/addons/ipblmonitor/js/3rdlib/jqGrid/js/i18n/grid.locale-en.js" type="text/javascript"></script>
<script src="../modules/addons/ipblmonitor/js/3rdlib/jqGrid/js/jquery.jqgrid.src.js" type="text/javascript"></script>
<script src="../modules/addons/ipblmonitor/js/3rdlib/jqGrid/plugins/jquery.contextmenu.js" type="text/javascript"></script>
<script src="../modules/addons/ipblmonitor/js/3rdlib/jqGrid/js/jqmodal.js" type="text/javascript"></script>
<script src="../modules/addons/ipblmonitor/js/3rdlib/jqGrid/js/grid.subgrid.js" type="text/javascript"> </script>


<script type="text/javascript" src="../modules/addons/ipblmonitor/js/3rdlib/jquery-loadmask-0.4/jquery.loadmask.js"></script>
<link rel="stylesheet" href="../modules/addons/ipblmonitor/js/3rdlib/jquery-loadmask-0.4/jquery.loadmask.css"  type="text/css"/>




<link type="text/css" href="../modules/addons/ipblmonitor/js/3rdlib/jquery-ui-1.10.1/css/cupertino/jquery-ui.css" rel="Stylesheet" />
<link type="text/css" href="../modules/addons/ipblmonitor/css/common.css" rel="Stylesheet" />


    <script type="text/javascript" src="../modules/addons/ipblmonitor/js/common.js"></script>
<script type="text/javascript" src="../modules/addons/ipblmonitor/js/adminui.js"></script>

<script type="text/javascript" src="../modules/addons/ipblmonitor/js/3rdlib/Notify.js"></script>

    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

    <link href="../modules/addons/ipblmonitor/js/3rdlib/jquery-ui-iconfont/jquery-ui-1.12.icon-font.css" rel="stylesheet" type="text/css" />


{/literal}  
{/if}


{if $adminbody}

    <div id="ipblmonitor_loader" style="height:500px;">&nbsp;</div>
<div id="ipblmonitor_ctrl_panel" style="display:none;font-size:smaller;">




<div id="maintabs" style="padding:0;width:100%;">
    <ul>
        <li><a href="#dashboard">DashBoard</a></li>
        <li><a href="#dnsbllistedip">Black Listed IPs</a></li>
        <li><a href="#monitoredip">Monitored IPs</a></li>
        <li><a href="#blchecker">BL Checker</a></li>
        <li><a href="#dnsblservers">DNSBL Servers</a></li>
        <li><a href="#settings">Settings</a></li>


    </ul>

<div id="mainpanel" style="padding:0;width:100%;">

    <!-- DASHBOARD TAB-->
    <div  role="tabpanel" class="tab-pane active" id="dashboard" style="width:100%;padding:20px;">
            <div class="row">

                <div class="col-md-12">
                    <div class="panel panel-info ">
                        <div class="panel-heading"></div>
                        <div class="panel-body" id="dash_last_detect">

                            <div class="container">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div id="ipinfochart" style="width: 100%; height: 200px;"></div>

                                    </div>
                                    <div class="col-sm-6">
                                        <div id="piechart" style="width: 100%; height: 200px;"></div>
                                    </div>
                                </div>
                                    <div class="row">
                                    <div class="col-sm-12">
                                        <div id="toprblchart" style="width: 100%; height: 400px;"></div>
                                    </div>
                                </div>
                            </div>



                        </div>
                    </div>

                </div>
            </div>
    </div>


    <div id="dnsbllistedip" style="width:100%;padding:0;">
        <table style="width:100%;" id="dnsbllistedipGrid"></table>
        <div id="dnsbllistedipgridpager"></div>
    </div>


    <div id="monitoredip" style="padding:0;">
        <table style="width:100%;" id="monitoredipGrid"></table>
        <div id="monitoredipgridpager"></div>
    </div>

    <div id="blchecker" style="padding:0;">
        <div class="panel-body">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-3">
                <input type="blcheckip" class="form-control" id="blcheckip" placeholder="Enter IP or Domain name" name="blcheckip">
                    </div>
                    <div class="col-sm-9">
                <button class="btn btn-primary btn-md" id='btn_blcheckip'>Check</button>
                </div>




                </div>
                <div class="row">
                    <div class="col-sm-6" id="blcheckresult"><br>
                        <p>Total/BlackListed RBL Servers: <span id="totrbls">0</span>/<span style="color:red;" id="blackrbls">0</span></p>

                    </div>
                </div>
            </div>
            </div>


        <table style="width:100%;" id="blcheckerGrid"></table>
        <div id="blcheckergridpager"></div>

    </div>

    <div id="dnsblservers" style="padding:0;">
        <table style="width:100%;" id="dnsblserversGrid"></table>
        <div id="dnsblserversgridpager"></div>
    </div>


	<div id="settings" style="padding:10px;">

        <div class="panel-body">

            <div class="form-group">
                <label for="croncmd">Cron Jobs cmd</label>
                <input class="form-control" id="croncmd" placeholder="" name="croncmd">
            </div>

            <div class="form-group">
                <label for="checkfrequency">Check Frequency (in Hours)</label>
                <input class="form-control" id="checkfrequency" placeholder="" name="checkfrequency">

            </div>

            <div class="form-group">
                <label for="adminusername">WHMCS Admin User Name</label>
                <input class="form-control" id="adminusername" placeholder="" name="adminusername">

            </div>

            <div class="form-group">
                <label for="enable_notifyemails">Enable Notify Emails</label><br>
                <input type="checkbox" class="form-control" id="enable_notifyemails" placeholder="" name="enable_notifyemails"></input>

            </div>

            <div class="form-group">
                <label for="smtp_host">SMTP Server Host Name</label>
                <input class="form-control" id="smtp_host" placeholder="" name="smtp_host">

            </div>

            <div class="form-group">
                <label for="smtp_port">SMTP Server Port</label>
                <input class="form-control" id="smtp_port" placeholder="" name="smtp_port">

            </div>

            <div class="form-group">
                <label for="smtp_user">SMTP Server User Name</label>
                <input class="form-control" id="smtp_user" placeholder="" name="smtp_user">

            </div>

            <div class="form-group">
                <label for="smtp_password">SMTP Server Password</label>
                <input class="form-control" id="smtp_password" placeholder="" name="smtp_password">

            </div>




            <div class="form-group">
                <label for="notifyemails">Notify Emails (one email per line)</label>
                <textarea class="form-control" id="notifyemails" placeholder="" name="notifyemails" rows="10"></textarea>

            </div>

            <br><button class="btn btn-danger center-block" id='savesettings'>Save</button>



    </div>
  


</div>

</div>


</div>


</div>


{/if}
