<?php

defined("ABSPATH") or die("Direct access blocked.");

// Plugin Name: Simplistics Test Plugin
// Plugin URI:  https://developer.wordpress.org/plugins/the-basics/
// Description: Gather data from the Google Places API which will be saved and read back from the local database.
// Version:     1.0
// Author:      James Rodrigues

// API call setup.
$api_data = array(
    "key"     => "AIzaSyBAVgFPCtxIxLEE650t3feNZRBUU9iK3bM",
    "placeid" => "ChIJDaS6RAguK4gRgBZFZYOfAus"
);

$api_call = "https://maps.googleapis.com/maps/api/place/details/json?" . http_build_query($api_data);

// Make the call and save the content to an object.
$result = json_decode(file_get_contents($api_call), true);

// Check if the API returned an error and set up the database if it hasn't.
if(isset($result["error_message"])) {
    echo "Error fetching Places API data: " . $result["error_message"];
}
else {
    global $wpdb;
    $table = $wpdb->prefix . "simplisticstest"; 

    // Check if the table already exists and create it if it doesn't.
    // 'hash' field is used to store a truncated sha1 hash of the author's name, review text, and time to avoid inserting duplicates.
    if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
                    id tinyint NOT NULL AUTO_INCREMENT,   
                    author_name tinytext NOT NULL,
                    author_url varchar(255) NOT NULL,
                    language char(4) NOT NULL,
                    profile_photo_url varchar(255) NOT NULL,
                    rating smallint NOT NULL,
                    relative_time_description tinytext NOT NULL,
                    text text NOT NULL,
                    time int unsigned NOT NULL,
                    hash char(5) UNIQUE NOT NULL,
                    PRIMARY KEY  (id)
                ) $charset_collate;";

        require_once(ABSPATH . "wp-admin/includes/upgrade.php");
        dbDelta($sql);
    }

    // Update the database with API data.
    foreach($result["result"]["reviews"] as $review) {
        $data = array(
            "author_name"               => $review["author_name"],
            "author_url"                => $review["author_url"],
            "language"                  => $review["language"],
            "profile_photo_url"         => $review["profile_photo_url"],
            "rating"                    => $review["rating"],
            "relative_time_description" => $review["relative_time_description"],       
            "text"                      => $review["text"],
            "time"                      => $review["time"],
            "hash"                      => substr(hash("sha1", $review["author_name"] . $review["time"] . $review["text"]), 0, 5)
        );

        $wpdb->insert($table, $data);
    }
}

// Get data from the database and pack it in some HTML.
function get_reviews($atts) {
    global $wpdb;
    $table = $wpdb->prefix . "simplisticstest";

    $db_reviews = $wpdb->get_results("SELECT author_name, rating, relative_time_description, text FROM $table");
    $html = "<div id='simplistics_reviews'>";

    if($db_reviews) {
        foreach($db_reviews as $review) {
            $html .= "<div id='review_author'>$review->author_name</div>
                      <div id='review_rating'>$review->rating</div>
                      <div id='review_time'>$review->relative_time_description</div>
                      <div id='review_text'>$review->text</div>";
        }
    }

    $html .= "</div>";
    return $html;
}

// Export the function as a shortcode.
add_shortcode("simplistics_reviews", "get_reviews");

?>