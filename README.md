# SheapGamer RSS Content Fetcher

## Description

The SheapGamer RSS Content Fetcher is a powerful WordPress plugin designed to automate the process of creating posts from external RSS feeds. It streamlines content curation by fetching articles from your specified RSS feed and converting them into WordPress posts. This plugin now comes with integrated Gemini AI capabilities to intelligently generate SEO-friendly slugs and relevant tags for your new posts, as well as correcting problematic post titles

Say goodbye to manual content import and inconsistent post metadata, and welcome a smarter, more efficient way to keep your WordPress site updated with fresh content\!

<img width="778" alt="Screenshot 2568-06-27 at 21 21 04" src="https://github.com/user-attachments/assets/164cb591-e793-467a-996f-8a0a686bbd37" />


## Features

  * **RSS Feed Integration:** Easily configure an RSS feed URL to fetch content from.

  * **Automated Post Creation:** Converts fetched RSS items into new WordPress posts.

  * **Configurable Post Limit:** Set a limit on the number of latest posts to fetch.

  * **Gemini AI for Slugs & Tags:** Utilizes Google's Gemini AI to generate highly relevant and SEO-friendly slugs and tags based on post titles and content.

  * **Intelligent Title Correction:** Automatically detects and suggests new, meaningful titles using Gemini AI for posts that have problematic or URL-based titles (e.g., titles containing "https://www").

  * **Exceprt Generation:** Utilizes Google's Gemini AI to generate SEO friendly Thai Exceprt

  * **Featured Image Support:** Attempts to extract and set featured images from RSS feed items.

  * **Activity Logging:** Comprehensive logging of fetch operations, post creation, and any errors directly within your WordPress admin.

  * **User-Friendly Admin Interface:** Simple settings page for quick setup and management.

## Installation

1.  **Download:** Download the plugin ZIP file from the [plugin URI](https://sheapgamer.com/) (or wherever you obtain the plugin).

2.  **Upload via WordPress Admin:**

      * Go to your WordPress dashboard.

      * Navigate to `Plugins > Add New`.

      * Click the `Upload Plugin` button at the top.

      * Choose the downloaded ZIP file and click `Install Now`.

3.  **Activate:** After installation, click `Activate Plugin`.

4.  **Manual Installation (FTP):**

      * Unzip the plugin file.

      * Upload the `sheapgamer-rss-fetcher` folder to the `/wp-content/plugins/` directory on your server.

      * Go to `Plugins` in your WordPress admin and activate the "SheapGamer RSS Content Fetcher" plugin.

## Usage

Once activated, you will find the plugin settings under `Settings > RSS Fetcher` in your WordPress admin menu.

## Configuration

1.  **Access Settings:** Go to `SheapGamer RSS Fetcher` in your WordPress admin menu.

2.  **RSS Feed URL:** Enter the full URL to the RSS feed you wish to fetch content from (e.g., `https://example.com/feed/`).

3.  **Number of Posts to Fetch (Max 25):** Specify how many of the latest posts to retrieve from the RSS feed during each fetch operation.

4.  **Gemini API Key:** **Crucial for AI features\!** Enter your Gemini API Key here. You can obtain this key from [Google AI Studio](https://aistudio.google.com/)  or similar Google Cloud Platform credentials. This key enables the automatic slug/tag generation and intelligent title correction features. Without it, the plugin will use fallback logic for slugs and tags.

5.  **Save Changes:** Click the "Save Changes" button to apply your settings.

## Activity Logs

The plugin includes an "Activity Log" section on its settings page. This log provides real-time feedback on fetching operations, post creation, Gemini API calls, and any errors encountered. This is invaluable for troubleshooting and monitoring the plugin's performance. You can also clear the logs from this section.

## Changelog

**Version 1.2.0 (Latest)**

  * Added Gemini AI integration for intelligent post title correction. Titles containing "https://www" will now be re-suggested by Gemini AI.

  * Updated Gemini API calls to use `gemini-2.0-flash` model for improved performance and cost-efficiency.

**Version 1.1.0**

  * Initial release with basic RSS fetching and post creation.

  * Introduced Gemini AI for automatic slug and tag generation.

  * Implemented featured image fetching from RSS feeds.

  * Added comprehensive activity logging.

## License

This project is licensed under the MIT License.
