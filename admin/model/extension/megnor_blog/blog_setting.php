<?php
class ModelExtensionMegnorBlogBlogSetting extends Model { 
	// Get blog home SEO url
	public function getBlogHomeSeoUrls() {
		$blog_home_seo_url_data = array();
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query = 'extension/megnor_blog/home'");

		foreach ($query->rows as $result) {
			$blog_home_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $blog_home_seo_url_data;
	}
	
		
	// Save blog home SEO url
	public function saveBlogHomeKeyword($code, $data, $store_id = 0) {
	
	$this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query = 'extension/megnor_blog/home'");	
	
		if (isset($data['blog_home_seo_url'])) {
			foreach ($data['blog_home_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (trim($keyword)) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'extension/megnor_blog/home', keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}
	}


}