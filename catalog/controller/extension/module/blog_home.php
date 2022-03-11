<?php
class ControllerExtensionModuleBlogHome extends Controller {
	public function index($setting) {

		
		
		static $module = 0;
		
		$this->load->language('megnor_blog/blog');
		$this->load->model('extension/megnor_blog/blog');
		$this->load->model('tool/image');
		
		$data = array(
			'start' => 0,
			'limit' => $setting['limit']
		);
		
		$data['characters'] = $setting['characters'];
		$data['carousel'] = $setting['carousel'];
		$data['columns'] = $setting['columns'];
		$data['thumb'] = $setting['thumb'];
		
		if ($setting['carousel']) {
		$this->document->addScript('catalog/view/javascript/jquery/owl.carousel.min.js');
		$this->document->addStyle('catalog/view/javascript/jquery/owl-carousel/owl.carousel.css');
		}
		
		if ($setting['category']) {
		$results = $this->model_extension_megnor_blog_blog->getBlogsByBlogCategoryId($setting['category'],0,$setting['limit']);
		} else {
		$results = $this->model_extension_megnor_blog_blog->getLatestBlogs($data);	
		}
		
		foreach ($results as $result) {
      		$data['posts'][] = array(
        		'title' => $result['title'],
				'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'author' => $result['author'],
				'comment_total' => $this->model_extension_megnor_blog_blog->getTotalCommentsByBlogId($result['blog_id']),
				'date_added_full' => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'description' => utf8_substr(strip_tags(html_entity_decode($result['short_description'], ENT_QUOTES, 'UTF-8')), 0, $setting['characters']) . '..',
				'count_read' => $result['count_read'],
				'image'   		=> $this->model_tool_image->resize($result['image'], $setting['width'], $setting['height']),
	    		'href'  => $this->url->link('extension/megnor_blog/blog', 'blog_id=' . $result['blog_id'])
      		);
    	}
		
		if (!empty($setting['title'][$this->config->get('config_language_id')])) {
			$data['heading_title_latest'] = html_entity_decode($setting['title'][$this->config->get('config_language_id')], ENT_QUOTES, 'UTF-8');
		} else {
			$data['heading_title_latest'] = $this->language->get('heading_title_latest');
		}
		
		$data['blog_show_all'] = $this->url->link('extension/megnor_blog/home');
		$data['text_show_all'] = $this->language->get('text_show_all');
		$data['text_posted_on'] = $this->language->get('text_posted_on');
		$data['text_posted_by'] = $this->language->get('text_posted_by');
		$data['text_read'] = $this->language->get('text_read');
		$data['text_comments'] = $this->language->get('text_comments');
		$data['text_not_found'] = $this->language->get('text_not_found');
		$data['text_read_more'] = $this->language->get('text_read_more');
		$data['date_added_status'] = $this->config->get('blogsetting_date_added');
		$data['comments_count_status'] = $this->config->get('blogsetting_comments_count');
		$data['page_view_status'] = $this->config->get('blogsetting_page_view');
		$data['author_status'] = $this->config->get('blogsetting_author');

		$data['module'] = $module++;

		return $this->load->view('extension/module/blog_home', $data);
	}
}