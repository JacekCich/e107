<?php


//v2.x Standard for extending admin areas.


class social_admin
{
	private $twitterActive = false;


	function __construct()
	{
		$pref = e107::pref('core','social_login');
		$this->twitterActive = vartrue($pref['Twitter']['keys']['key']);
	}


	/**
	 * Extend Admin-ui Parameters
	 * @param $ui admin-ui object
	 * @return array
	 */
	public function config($ui)
	{
		$action     = $ui->getAction(); // current mode: create, edit, list
		$type       = $ui->getEventName(); // 'wmessage', 'news' etc.

		$config = array();

		//TODO Add support for type='method'. (ie. extending the form-handler. )

		switch($type)
		{
			case "page":
			case "news":

				if($this->twitterActive == true)
				{
					$config['fields']['twitter'] =   array ( 'title' =>"Post to Twitter", 'type' => 'text', 'tab'=>1,  'writeParms'=> array('size'=>'xxlarge', 'placeholder'=>'Type your tweet here.'), 'width' => 'auto', 'help' => '', 'readParms' => '', 'class' => 'left', 'thclass' => 'left',  );
				}
				break;
		}

		//Note: 'twitter' will be returned as $_POST['x_social_twitter']. ie. x_{PLUGIN_FOLDER}_{YOURKEY}

		return $config;

	}


	/**
	 * Process Posted Data.
	 * @param $ui admin-ui object
	 */
	public function process($ui)
	{
		$data = $ui->getPosted();
		//e107::getHybridAuth('twitter');
		e107::getMessage()->addDebug(print_a($data,true));
	}



}




?>