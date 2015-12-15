<?php
	namespace App;
	use App\Render;
	class System {

		private $router;
		private $render;
		private $db;
		private $auth;

		public function __construct() {
			$this->loadEnv();
			$this->compileLess();
		}
		
		public function setRouter(\AltoRouter $router) {
			$this->router = $router;
		}

		public function getRouter() {
			return $this->router;
		}

		public function getRender() {
			if(!$this->render)
				$this->render = new Render;
			return $this->render;
		}

		public function getDb() {
			if(!$this->db)
				$this->db = new Db();
			return $this->db;
		}

		public function getAuth() {
			if(!$this->auth)
				$this->auth = new Auth;
			return $this->auth;
		}

		public function render($template, $vars = null) {
			$middleVars = $this->getMiddleVars();
			if($vars)
				$responseVars = array_merge($vars, $middleVars);
			else
				$responseVars = $middleVars;

			$this->getRender()->render($template, $responseVars);
		}

		public function redirect($to) {
			if(!headers_sent()) {
				header('Location: ' . $this->router->generate($to));
				exit;
			} else
				$this->getRender()->error(500, 'Trying to redirect but headers has already been sent');
		}

		public function renderRouterMatch() {
			$match = $this->router->match();

			// Match found
			if ($match) {
				$auth = $this->getAuth();
				if(in_array($match['name'], $auth->getProtectedRoutes()) && !$auth->isAuthenticated()) {
					$this->getRender()->error(401);
				} else {
					if(is_callable( $match['target'] ) )
						call_user_func_array( $match['target'], $match['params'] ); 
					
					elseif(list($controller, $action) = explode( '#', $match['target'] )) {
						if ( is_callable(array($controller, $action)) ) {
							$MethodChecker = new \ReflectionMethod($controller,$action);
							if($MethodChecker->isStatic())
								call_user_func_array(array($controller,$action), array($match['params']));
							else {
								call_user_func_array(array(new $controller, $action), array($match['params']));
							}
						}
						else
							$this->getRender()->error(500, 'Could not call ' . $controller . '#' . $action);
					}

					else
						$this->getRender()->error(500, 'Matching route found but could not execute action');
				}
			}
			else
				$this->getRender()->error(404);
		}

		private function getMiddleVars() {
			$middleVars = array('version' => getenv('VERSION'));
			$middleVars['authenticated'] = $this->getAuth()->isAuthenticated();
			return $middleVars;
		}

		private function loadEnv() {
			try {
				$this->env = (new \josegonzalez\Dotenv\Loader('.env'))
							->parse()
							->putenv();
				} catch(\InvalidArgumentException $e) {
					$this->getRender()->error(500, 'Failed to open envirnomental file. Make sure it exists in app roop (/.env)', $e);
					die();
				}
		}

		private function compileLess() {
			$less = new \lessc;
			try {
				$less->checkedCompile(__DIR__ . "/../resources/less/main.less", __DIR__ . "/../resources/css/main.css");
			} catch(\Exception $e) {
				$this->render->error(500, $e->getMessage());
			}
		}

	}