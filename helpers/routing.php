<?php

function wpr_admin_menu()
{
	global $wpr_routes;
	add_menu_page('Newsletters','Newsletters','manage_newsletters',__FILE__);

	//TODO: Refactor to use the new standard template rendering function for all pages.
	add_submenu_page(__FILE__,'Dashboard','Dashboard','manage_newsletters',__FILE__,"wpr_dashboard");

	$admin_pages_definitions = $wpr_routes;
	$admin_pages_definitions = apply_filters("_wpr_menu_definition",$admin_pages_definitions);
	foreach ($admin_pages_definitions as $definition)
	{
		add_submenu_page(__FILE__,$definition['page_title'],$definition['menu_title'],$definition['capability'],$definition['menu_slug'],$definition['callback']);
	}
}


function _wpr_handle_post()
{
        if (count($_POST)>0 && isset($_POST['wpr_form']))
        {
            $formName = $_POST['wpr_form'];
            $actionName = "_wpr_".$formName."_post";
            $default_handler_name = $actionName."_handler";
            add_action($actionName,$default_handler_name);
            do_action($actionName);
        }
}


function _wpr_render_view()
{
        global $wpr_globals;
        $plugindir = $GLOBALS['WPR_PLUGIN_DIR'];

        $currentView = _wpr_get("_wpr_view");

        extract($wpr_globals);

        $viewfile ="$plugindir/views/".$currentView.".php";

        if (is_file($viewfile)) {
	        require($viewfile); // this statement is necessarily a require and not an include. we want feedback when the file is not found.
	    }
        else
            throw new ViewFileNotFoundException();
}



class Routing {


    private static function legacyInit() {
    	global $wpr_routes;
	    $admin_page_definitions = $wpr_routes;

		foreach ($admin_page_definitions as $item)
		{
			if (isset($item['legacy']) && $item['legacy']===0)
			{
				$slug = str_replace("_wpr/","",$item['menu_slug']);
				$actionName = "_wpr_".$slug."_handle";
				$handler = "_wpr_".$slug."_handler";
				add_action($actionName,$handler);
			}
		}

    }

    public static function init() {

        global $wpr_routes;
        Routing::legacyInit();

        _wpr_handle_post();

        $path = $_GET['page'];

        $method_to_invoke = self::getMethodToInvoke();

        if (self::whetherControllerMethodExists($method_to_invoke)) {
            self::callControllerMethod($method_to_invoke);
        }

    }

    private static function getMethodToInvoke()
    {
        global $wpr_routes;
        $method_to_invoke = "";


        $current_path = trim($_GET['page']);


        if (self::whetherLegacyURL($current_path)) {
            return;
        }



        if (self::whetherPathExists($current_path)) {

            $method_to_invoke = $wpr_routes[$current_path]['controller'];


            if (self::whetherSubPageRequested($current_path)) {

                $subpage_name = self::getSubPageName();

                if (self::whetherSubPageExists($current_path, $subpage_name)) {

                    $method_to_invoke = $wpr_routes[$current_path]['children'][$subpage_name];

                }
                else
                    throw new UnknownSubPageRequestedException("Unknown sub page requested");
            }
        }
        else
            throw new DestinationControllerNotFoundException("Unknown destination invoked: $current_path");

        return $method_to_invoke;
    }

    private static function whetherLegacyURL($current_path)
    {
        return 0 < preg_match("@^wpresponder/@", $current_path);
    }

    private static function whetherSubPageExists($current_path, $action)
    {
        global $wpr_routes;
        return isset($wpr_routes[$current_path]['children'][$action]);
    }

    private static function getSubPageName()
    {
        $action = $_GET['action'];
        $action = preg_replace('@[^a-zA-Z0-9_]@', '', $action);
        return $action;
    }

    private static function whetherSubPageRequested()
    {
        return isset($_GET['action']);
    }

    private static function whetherPathExists($current_path)
    {
        global $wpr_routes;
        return isset($current_path) && isset($wpr_routes[$current_path]) && isset($wpr_routes[$current_path]['controller']);
    }

    public static function whetherControllerMethodExists($methodToCall)
    {
        return function_exists($methodToCall);
    }

    private  static function callControllerMethod($methodToCall)
    {
        do_action('_wpr_router_pre_callback');
        call_user_func($methodToCall);
        do_action('_wpr_router_post_callback');
    }

    public static function isWPRAdminPage() {
        $res = preg_match("@^wpresponder/.*@",$_GET['page']);
        $result = isset($_GET['page']) && ( 0 != preg_match("@^wpresponder/.*@",$_GET['page']) || preg_match("@^_wpr/.*@",$_GET['page']));
        return $result;
    }

    public static function url($string,$arguments=array())
	{
		$queryString = "";
		if (count($arguments) > 0)
		{
			foreach ($arguments as $name=>$value)
			{
				$queryString .= sprintf("&%s=%s",$name,$value);
			}
		}
		return "admin.php?page=_wpr/".$string.$queryString;
	}

	public static function newsletterHome()
	{
		return Routing::url("newsletter");
	}

}


class DestinationControllerNotFoundException extends Exception
{

}


class UnknownSubPageRequestedException extends Exception {

}

class ViewFileNotFoundException extends Exception {

}