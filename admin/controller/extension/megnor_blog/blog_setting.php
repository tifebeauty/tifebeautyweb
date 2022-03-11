<?php
class ControllerExtensionMegnorBlogBlogSetting extends Controller { 
	private $error = array();

	public function index() {
		
		$this->install();	
		
		$this->load->language('megnor_blog/blog_setting');

		$this->document->setTitle($this->language->get('heading_title'));
		 
		$this->load->model('extension/megnor_blog/blog_setting');
		
		$this->load->model('setting/setting');
				
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

			$this->model_extension_megnor_blog_blog_setting->saveBlogHomeKeyword('blogsetting', $this->request->post);
			
			$this->model_setting_setting->editSetting('blogsetting', $this->request->post);
			
			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('extension/megnor_blog/blog_setting', 'user_token=' . $this->session->data['user_token'], true));
		}

		$data['user_token'] = $this->session->data['user_token'];

 		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		
		$url = '';
					
  		$data['breadcrumbs'] = array();

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'], true)
   		);

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('extension/megnor_blog/blog_setting', 'user_token=' . $this->session->data['user_token'] . $url, true)
   		);
		
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}
							
		$data['action'] = $this->url->link('extension/megnor_blog/blog_setting', 'user_token=' . $this->session->data['user_token'], true);
		
		$this->load->model('localisation/language');
		
		$data['languages'] = $this->model_localisation_language->getLanguages();
		
		$this->load->model('setting/store');
		
		$data['stores'] = array();
		
		$data['stores'][] = array(
			'store_id' => 0,
			'name'     => $this->language->get('text_default')
		);
		
		$stores = $this->model_setting_store->getStores();

		foreach ($stores as $store) {
			$data['stores'][] = array(
				'store_id' => $store['store_id'],
				'name'     => $store['name']
			);
		}
		
		if (isset($this->request->post['blog_home_seo_url'])) {
			$data['blog_home_seo_url'] = $this->request->post['blog_home_seo_url'];
		} else {
			$data['blog_home_seo_url'] = $this->model_extension_megnor_blog_blog_setting->getBlogHomeSeoUrls();
		}
		

		
		if (isset($this->request->post['blogsetting_home_title'])) {
			$data['blogsetting_home_title'] = $this->request->post['blogsetting_home_title'];
		} else {
			$data['blogsetting_home_title'] = $this->config->get('blogsetting_home_title');
		}
		
		if (isset($this->request->post['blogsetting_home_page_title'])) {
			$data['blogsetting_home_page_title'] = $this->request->post['blogsetting_home_page_title'];
		} else {
			$data['blogsetting_home_page_title'] = $this->config->get('blogsetting_home_page_title');
		}
		
		if (isset($this->request->post['blogsetting_home_description'])) {
			$data['blogsetting_home_description'] = $this->request->post['blogsetting_home_description'];
		} else {
			$data['blogsetting_home_description'] = $this->config->get('blogsetting_home_description');
		}
		
		if (isset($this->request->post['blogsetting_home_meta_description'])) {
			$data['blogsetting_home_meta_description'] = $this->request->post['blogsetting_home_meta_description'];
		} else {
			$data['blogsetting_home_meta_description'] = $this->config->get('blogsetting_home_meta_description');
		}
		
		if (isset($this->request->post['blogsetting_home_meta_keyword'])) {
			$data['blogsetting_home_meta_keyword'] = $this->request->post['blogsetting_home_meta_keyword'];
		} else {
			$data['blogsetting_home_meta_keyword'] = $this->config->get('blogsetting_home_meta_keyword');
		}
		
		if (isset($this->request->post['blogsetting_blogs_per_page'])) {
			$data['blogsetting_blogs_per_page'] = $this->request->post['blogsetting_blogs_per_page'];
		} elseif ($this->config->has('blogsetting_blogs_per_page')) {
			$data['blogsetting_blogs_per_page'] = $this->config->get('blogsetting_blogs_per_page');
		} else {
			$data['blogsetting_blogs_per_page'] = 5;
		}

		if (isset($this->request->post['blogsetting_layout'])) {
			$data['blogsetting_layout'] = $this->request->post['blogsetting_layout'];
		} elseif ($this->config->has('blogsetting_layout')) {
			$data['blogsetting_layout'] = $this->config->get('blogsetting_layout');
		} else {
			$data['blogsetting_layout'] = 1;
		}
		
		if (isset($this->request->post['blogsetting_thumbs_w'])) {
			$data['blogsetting_thumbs_w'] = $this->request->post['blogsetting_thumbs_w'];
		} elseif ($this->config->has('blogsetting_thumbs_w')) {
			$data['blogsetting_thumbs_w'] = $this->config->get('blogsetting_thumbs_w');
		} else {
			$data['blogsetting_thumbs_w'] = 424;
		}
		
		if (isset($this->request->post['blogsetting_thumbs_h'])) {
			$data['blogsetting_thumbs_h'] = $this->request->post['blogsetting_thumbs_h'];
		} elseif ($this->config->has('blogsetting_thumbs_h')) {
			$data['blogsetting_thumbs_h'] = $this->config->get('blogsetting_thumbs_h');
		} else {
			$data['blogsetting_thumbs_h'] = 848;
		}
		
		if (isset($this->request->post['blogsetting_post_thumbs_w'])) {
			$data['blogsetting_post_thumbs_w'] = $this->request->post['blogsetting_post_thumbs_w'];
		} elseif ($this->config->has('blogsetting_post_thumbs_w')) {
			$data['blogsetting_post_thumbs_w'] = $this->config->get('blogsetting_post_thumbs_w');
		} else {
			$data['blogsetting_post_thumbs_w'] = 848;
		}
		
		if (isset($this->request->post['blogsetting_post_thumbs_h'])) {
			$data['blogsetting_post_thumbs_h'] = $this->request->post['blogsetting_post_thumbs_h'];
		} elseif ($this->config->has('blogsetting_post_thumbs_h')) {
			$data['blogsetting_post_thumbs_h'] = $this->config->get('blogsetting_post_thumbs_h');
		} else {
			$data['blogsetting_post_thumbs_h'] = 424;
		}

		if (isset($this->request->post['blogsetting_date_added'])) {
			$data['blogsetting_date_added'] = $this->request->post['blogsetting_date_added'];
		} elseif ($this->config->has('blogsetting_date_added')) {
			$data['blogsetting_date_added'] = $this->config->get('blogsetting_date_added');
		} else {
			$data['blogsetting_date_added'] = 1;
		}
		
		if (isset($this->request->post['blogsetting_comments_count'])) {
			$data['blogsetting_comments_count'] = $this->request->post['blogsetting_comments_count'];
		} elseif ($this->config->has('blogsetting_comments_count')) {
			$data['blogsetting_comments_count'] = $this->config->get('blogsetting_comments_count');
		} else {
			$data['blogsetting_comments_count'] = 1;
		}

		if (isset($this->request->post['blogsetting_page_view'])) {
			$data['blogsetting_page_view'] = $this->request->post['blogsetting_page_view'];
		} elseif ($this->config->has('blogsetting_page_view')) {
			$data['blogsetting_page_view'] = $this->config->get('blogsetting_page_view');
		} else {
			$data['blogsetting_page_view'] = 1;
		}

		if (isset($this->request->post['blogsetting_author'])) {
			$data['blogsetting_author'] = $this->request->post['blogsetting_author'];
		} elseif ($this->config->has('blogsetting_author')) {
			$data['blogsetting_author'] = $this->config->get('blogsetting_author');
		} else {
			$data['blogsetting_author'] = 1;
		}
		
		if (isset($this->request->post['blogsetting_share'])) {
			$data['blogsetting_share'] = $this->request->post['blogsetting_share'];
		} elseif ($this->config->has('blogsetting_share')) {
			$data['blogsetting_share'] = $this->config->get('blogsetting_share');
		} else {
			$data['blogsetting_share'] = 1;
		}

		if (isset($this->request->post['blogsetting_post_thumb'])) {
			$data['blogsetting_post_thumb'] = $this->request->post['blogsetting_post_thumb'];
		} elseif ($this->config->has('blogsetting_post_thumb')) {
			$data['blogsetting_post_thumb'] = $this->config->get('blogsetting_post_thumb');
		} else {
			$data['blogsetting_post_thumb'] = 1;
		}

		if (isset($this->request->post['blogsetting_rel_characters'])) {
			$data['blogsetting_rel_characters'] = $this->request->post['blogsetting_rel_characters'];
		} elseif ($this->config->has('blogsetting_rel_characters')) {
			$data['blogsetting_rel_characters'] = $this->config->get('blogsetting_rel_characters');
		} else {
			$data['blogsetting_rel_characters'] = 100;
		}
		
		if (isset($this->request->post['blogsetting_rel_blog_per_row'])) {
			$data['blogsetting_rel_blog_per_row'] = $this->request->post['blogsetting_rel_blog_per_row'];
		} elseif ($this->config->has('blogsetting_rel_blog_per_row')) {
			$data['blogsetting_rel_blog_per_row'] = $this->config->get('blogsetting_rel_blog_per_row');
		} else {
			$data['blogsetting_rel_blog_per_row'] = 2;
		}
		
		if (isset($this->request->post['blogsetting_rel_thumb'])) {
			$data['blogsetting_rel_thumb'] = $this->request->post['blogsetting_rel_thumb'];
		} elseif ($this->config->has('blogsetting_rel_thumb')) {
			$data['blogsetting_rel_thumb'] = $this->config->get('blogsetting_rel_thumb');
		} else {
			$data['blogsetting_rel_thumb'] = 1;
		}
		
		if (isset($this->request->post['blogsetting_rel_thumbs_w'])) {
			$data['blogsetting_rel_thumbs_w'] = $this->request->post['blogsetting_rel_thumbs_w'];
		} elseif ($this->config->has('blogsetting_rel_thumbs_w')) {
			$data['blogsetting_rel_thumbs_w'] = $this->config->get('blogsetting_rel_thumbs_w');
		} else {
			$data['blogsetting_rel_thumbs_w'] = 408;
		}

		if (isset($this->request->post['blogsetting_rel_thumbs_h'])) {
			$data['blogsetting_rel_thumbs_h'] = $this->request->post['blogsetting_rel_thumbs_h'];
		} elseif ($this->config->has('blogsetting_rel_thumbs_h')) {
			$data['blogsetting_rel_thumbs_h'] = $this->config->get('blogsetting_rel_thumbs_h');
		} else {
			$data['blogsetting_rel_thumbs_h'] = 204;
		}
	
		
		if (isset($this->request->post['blogsetting_post_date_added'])) {
			$data['blogsetting_post_date_added'] = $this->request->post['blogsetting_post_date_added'];
		} elseif ($this->config->has('blogsetting_post_date_added')) {
			$data['blogsetting_post_date_added'] = $this->config->get('blogsetting_post_date_added');
		} else {
			$data['blogsetting_post_date_added'] = 1;
		}

		if (isset($this->request->post['blogsetting_post_comments_count'])) {
			$data['blogsetting_post_comments_count'] = $this->request->post['blogsetting_post_comments_count'];
		} elseif ($this->config->has('blogsetting_post_comments_count')) {
			$data['blogsetting_post_comments_count'] = $this->config->get('blogsetting_post_comments_count');
		} else {
			$data['blogsetting_post_comments_count'] = 1;
		}

		if (isset($this->request->post['blogsetting_post_page_view'])) {
			$data['blogsetting_post_page_view'] = $this->request->post['blogsetting_post_page_view'];
		} elseif ($this->config->has('blogsetting_post_page_view')) {
			$data['blogsetting_post_page_view'] = $this->config->get('blogsetting_post_page_view');
		} else {
			$data['blogsetting_post_page_view'] = 1;
		}

		if (isset($this->request->post['blogsetting_post_author'])) {
			$data['blogsetting_post_author'] = $this->request->post['blogsetting_post_author'];
		} elseif ($this->config->has('blogsetting_post_author')) {
			$data['blogsetting_post_author'] = $this->config->get('blogsetting_post_author');
		} else {
			$data['blogsetting_post_author'] = 1;
		}
		
		if (isset($this->request->post['blogsetting_comment_per_page'])) {
			$data['blogsetting_comment_per_page'] = $this->request->post['blogsetting_comment_per_page'];
		} elseif ($this->config->has('blogsetting_comment_per_page')) {
			$data['blogsetting_comment_per_page'] = $this->config->get('blogsetting_comment_per_page');
		} else {
			$data['blogsetting_comment_per_page'] = 5;
		}
		
		if (isset($this->request->post['blogsetting_comment_approve'])) {
			$data['blogsetting_comment_approve'] = $this->request->post['blogsetting_comment_approve'];
		} elseif ($this->config->has('blogsetting_comment_approve')) {
			$data['blogsetting_comment_approve'] = $this->config->get('blogsetting_comment_approve');
		} else {
			$data['blogsetting_comment_approve'] = 1;
		}
		
		if (isset($this->request->post['blogsetting_comment_notification'])) {
			$data['blogsetting_comment_notification'] = $this->request->post['blogsetting_comment_notification'];
		} elseif ($this->config->has('blogsetting_comment_notification')) {
			$data['blogsetting_comment_notification'] = $this->config->get('blogsetting_comment_notification');
		} else {
			$data['blogsetting_comment_notification'] = 1;
		}
		
		if (isset($this->request->post['blogsetting_author_change'])) {
			$data['blogsetting_author_change'] = $this->request->post['blogsetting_author_change'];
		} elseif ($this->config->has('blogsetting_author_change')) {
			$data['blogsetting_author_change'] = $this->config->get('blogsetting_author_change');
		} else {
			$data['blogsetting_author_change'] = 1;
		}
		
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/megnor_blog/blog_setting', $data));
	}

	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/megnor_blog/blog_setting')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
	
	
	public function install() {
	$sql = " SHOW TABLES LIKE '" . DB_PREFIX . "blog' ";
		$query = $this->db->query( $sql );
		if( count($query->rows) <=0 ){ 
			$this->createTables();
			$this->load->language('megnor_blog/blog_setting');
			$this->session->data['success'] = $this->language->get('text_success_install');
		}
	}
	
	public function createTables() {
		
	$sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "blog` ( ";
	$sql .= "`blog_id` int(11) NOT NULL AUTO_INCREMENT, ";
	$sql .= "`allow_comment` int(1) NOT NULL DEFAULT '1', ";
	$sql .= "`count_read` int(11) NOT NULL DEFAULT '0', ";
	$sql .= "`sort_order` int(3) NOT NULL, ";
	$sql .= "`status` int(1) NOT NULL DEFAULT '1', ";
	$sql .= "`author` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL, ";
	$sql .= "`date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', ";
	$sql .= "`image` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL, ";
	$sql .= "PRIMARY KEY (`blog_id`) ";
	$sql .= ") ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=16 ; ";
	$this->db->query($sql);
	
	$sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "blog_category` ( ";
	$sql .= "`blog_category_id` int(11) NOT NULL AUTO_INCREMENT, ";
	$sql .= "`parent_id` int(11) NOT NULL DEFAULT '0', ";
	$sql .= "`sort_order` int(3) NOT NULL DEFAULT '0', ";
	$sql .= "`date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', ";
	$sql .= "`status` int(1) NOT NULL DEFAULT '1', ";
	$sql .= "PRIMARY KEY (`blog_category_id`) ";
	$sql .= ") ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=49 ; ";
	$this->db->query($sql);
	
	$sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "blog_category_description` ( ";
	$sql .= "`blog_category_id` int(11) NOT NULL, ";
	$sql .= "`language_id` int(11) NOT NULL, ";
	$sql .= "`name` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '', ";
	$sql .= "`page_title` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '', ";
	$sql .= "`meta_keywords` varchar(255) COLLATE utf8_bin NOT NULL, ";
	$sql .= "`meta_description` varchar(255) COLLATE utf8_bin NOT NULL, ";
	$sql .= "`description` text COLLATE utf8_bin NOT NULL, ";
	$sql .= "PRIMARY KEY (`blog_category_id`,`language_id`), ";
	$sql .= "KEY `name` (`name`) ";
	$sql .= ") ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin; ";
	$this->db->query($sql);
	
	$sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "blog_category_to_layout` ( ";
	$sql .= "`blog_category_id` int(11) NOT NULL, ";
	$sql .= "`store_id` int(11) NOT NULL, ";
	$sql .= "`layout_id` int(11) NOT NULL, ";
	$sql .= "PRIMARY KEY (`blog_category_id`,`store_id`) ";
	$sql .= ") ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin; ";
	$this->db->query($sql);
	
	$sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "blog_category_to_store` ( ";
	$sql .= "`blog_category_id` int(11) NOT NULL, ";
	$sql .= "`store_id` int(11) NOT NULL, ";
	$sql .= "PRIMARY KEY (`blog_category_id`,`store_id`) ";
	$sql .= ") ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin; ";
	$this->db->query($sql);
	
	$sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "blog_comment` ( ";
	$sql .= "`blog_comment_id` int(11) NOT NULL AUTO_INCREMENT, ";
	$sql .= "`blog_id` int(11) NOT NULL DEFAULT '0', ";
	$sql .= "`name` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, ";
	$sql .= "`email` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, ";
	$sql .= "`comment` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, ";
	$sql .= "`date_added` datetime DEFAULT '0000-00-00 00:00:00', ";
	$sql .= "`status` int(1) NOT NULL DEFAULT '1', ";
	$sql .= "PRIMARY KEY (`blog_comment_id`) ";
	$sql .= ") ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=21 ; ";
	$this->db->query($sql);
	
	$sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "blog_description` ( ";
	$sql .= "`blog_id` int(11) NOT NULL, ";
	$sql .= "`language_id` int(11) NOT NULL, ";
	$sql .= "`title` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, ";
	$sql .= "`page_title` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, ";
	$sql .= "`meta_keyword` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, ";
	$sql .= "`meta_description` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, ";
	$sql .= "`short_description` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, ";
	$sql .= "`description` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, ";
	$sql .= "`tags` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL ";
	$sql .= ") ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin; ";
	$this->db->query($sql);
	
	$sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "blog_related` ( ";
	$sql .= "`parent_blog_id` int(11) NOT NULL DEFAULT '0', ";
	$sql .= "`child_blog_id` int(11) NOT NULL DEFAULT '0' ";
	$sql .= ") ENGINE=MyISAM DEFAULT CHARSET=latin1; ";
	$this->db->query($sql);
	
	$sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "blog_to_category` ( ";
	$sql .= "`blog_id` int(11) NOT NULL, ";
	$sql .= "`blog_category_id` int(11) NOT NULL, ";
	$sql .= "PRIMARY KEY (`blog_id`,`blog_category_id`) ";
	$sql .= ") ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin; ";
	$this->db->query($sql);
	
	$sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "blog_to_layout` ( ";
	$sql .= "`blog_id` int(11) NOT NULL, ";
	$sql .= "`store_id` int(11) NOT NULL, ";
	$sql .= "`layout_id` int(11) NOT NULL, ";
	$sql .= "PRIMARY KEY (`blog_id`,`store_id`) ";
	$sql .= ") ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin; ";
	$this->db->query($sql);
	
	
	
	$sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "blog_to_store` ( ";
	$sql .= "`blog_id` int(11) NOT NULL, ";
	$sql .= "`store_id` int(11) NOT NULL ";
	$sql .= ") ENGINE=MyISAM DEFAULT CHARSET=latin1; ";
	$this->db->query($sql);
	
	$sql  = "INSERT INTO  `" . DB_PREFIX . "layout` ( `layout_id` , `name` ) VALUES ( NULL , 'Blog' ); ";
	$query = $this->db->query( $sql );
		
	$id = $this->db->getLastId();
		
	$sql = "INSERT INTO `".DB_PREFIX."layout_route` (
				`layout_route_id` ,
				`layout_id` ,
				`store_id` ,
				`route`
				)
				VALUES (
				NULL , '".$id."', '0', 'extension/megnor_blog/%');
		";
		$query = $this->db->query( $sql );
	}
	

}