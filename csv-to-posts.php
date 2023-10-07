<?php
/*
Plugin Name: CSV to Articles
Description: Import up to 100 articles from a CSV file.
Author: Dehoucks
Version: 1.1
*/

function csv_to_posts_menu() {
    add_menu_page('CSV to Posts', 'CSV to Posts', 'manage_options', 'csv-to-posts', 'csv_to_posts_admin_page');
}

add_action('admin_menu', 'csv_to_posts_menu');

function csv_to_posts_admin_page() {
    ?>
    <div class="wrap" style="max-width: 600px; margin: 50px auto; background-color: #f9f9f9; padding: 30px; border-radius: 10px; box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.1);">
        <h2 style="font-size: 24px; border-bottom: 2px solid #007cba; padding-bottom: 10px; margin-bottom: 20px;">DeFiPress: Import Articles from CSV</h2>
        <p style="margin-bottom: 20px; font-size: 16px;">Choose a CSV file to import up to 100 articles into DeFiPress.</p>
        <p style="margin-bottom: 20px; font-size: 16px;">Format : Article,Title,Article Summary,Article Content,Image,Tags</p>
        <p style="margin-bottom: 20px; font-size: 16px;">In case the import encounters an issue, don't hesitate to restart it. Duplicates will be skipped.</p>
        <form id="csvImportForm" method="post" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 20px;">
            <input type="file" name="csv_file" accept=".csv" style="padding: 10px; font-size: 16px;">
            <button type="button" id="startImport" style="background-color: #007cba; color: #ffffff; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; transition: background-color 0.3s;">Upload and Import</button>
        </form>
        <div id="progressDisplay" style="margin-top: 20px; display: none;">
            <div id="progressBar" style="height: 20px; width: 0%; background-color: #007cba; border-radius: 5px;"></div>
            <p id="progressText" style="margin-top: 10px; font-size: 16px;">0% - 0 of 0 articles imported</p>
        </div>
        <div id="logsSection" style="margin-top: 30px;">
            <h3>Logs</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #007cba; color: #fff;">
                        <th>Article Title</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="logsBody">
                    <!-- Rows will be populated here dynamically -->
                </tbody>
            </table>
        </div>
        <style>
    .wrap {
        max-width: 600px;
        margin: 50px auto;
        background-color: #f9f9f9;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.1);
    }

    .wrap h2 {
        font-size: 24px;
        border-bottom: 2px solid #007cba;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .wrap p {
        margin-bottom: 20px;
        font-size: 16px;
    }

    .wrap input, .wrap button {
        padding: 10px;
        font-size: 16px;
    }

    .wrap button {
        background-color: #007cba;
        color: #ffffff;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .wrap button:disabled {
        background-color: #aaa;
        cursor: not-allowed;
    }

    #progressDisplay {
        margin-top: 20px;
        display: none;
    }

    #progressBar {
        height: 20px;
        background-color: #007cba;
        border-radius: 5px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    table thead tr {
        background-color: #007cba;
        color: #fff;
    }

    table tbody tr:nth-child(odd) {
        background-color: #f2f2f2;
    }
</style>
        <script>
            document.getElementById('startImport').addEventListener('click', async function() {
                const fileInput = document.querySelector('[name="csv_file"]');
                const logsBody = document.getElementById('logsBody');
                if (!fileInput.files.length) {
                    alert("Please select a CSV file first!");
                    return;
                }

                // First, upload the CSV and get the total count
                const formData = new FormData();
                formData.append('csv_file', fileInput.files[0]);
                formData.append('action', 'upload_csv_and_count');

                let response = await fetch(ajaxurl, { method: 'POST', body: formData });
                let data = await response.json();
                
                let totalArticles = data.total;
                let imported = 0;

                // Now, process articles in chunks
                while (imported < totalArticles) {
                    response = await fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=process_articles_chunk&start=${imported}`
                    });
                    
                    data = await response.json();
                    imported += data.importedThisRound;
                    
                    // Populate the logs
                    data.logs.forEach(log => {
                        const row = document.createElement('tr');
                        const titleCell = document.createElement('td');
                        titleCell.textContent = log.title;
                        const statusCell = document.createElement('td');
                        statusCell.textContent = log.status;
                        row.appendChild(titleCell);
                        row.appendChild(statusCell);
                        logsBody.appendChild(row);
                    });

                    updateProgress((imported / totalArticles) * 100, imported, totalArticles);
                }
            });
            function updateProgress(percentage, imported, total) {
                const progressBar = document.getElementById('progressBar');
                const progressText = document.getElementById('progressText');
                const progressDisplay = document.getElementById('progressDisplay');

                progressDisplay.style.display = 'block';
                progressBar.style.width = percentage + '%';
                progressText.textContent = percentage.toFixed(2) + '% - ' + imported + ' of ' + total + ' articles imported';
            }
        </script>
    </div>
    <?php
}

function upload_csv_and_count() {
    if (isset($_FILES['csv_file'])) {
        $uploadedfile = $_FILES['csv_file'];
        if ($uploadedfile['type'] == 'text/csv') {
            $handle = fopen($uploadedfile['tmp_name'], 'r');
            $totalArticles = 0;
            while (!feof($handle)) {
                $totalArticles++;
                fgetcsv($handle);
            }
            fclose($handle);
            
            // Store the uploaded CSV to a temporary location for processing
            move_uploaded_file($uploadedfile['tmp_name'], WP_CONTENT_DIR . '/uploads/temp.csv');
            
            echo json_encode(['total' => $totalArticles - 1]); // -1 to exclude header
        }
    }
    wp_die();
}

function process_articles_chunk() {
    ini_set('memory_limit', '256M');
    set_time_limit(300); // 5 minutes
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $chunkSize = 5; // Process 5 articles at a time, you can adjust this

    $handle = fopen(WP_CONTENT_DIR . '/uploads/temp.csv', 'r');
    
    // Skip articles before $start
    for ($i = 0; $i <= $start; $i++) {
        fgetcsv($handle);
    }

    $importedThisRound = 0;
    $logs = [];
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE && $importedThisRound < $chunkSize) {
        // Check for duplicate post by title
        $existing_post = get_page_by_title($data[0], OBJECT, 'post');
        if ($existing_post) {
            // Skip this iteration if post with same title exists, but update the logs
            $logs[] = ['title' => $data[0], 'status' => 'duplicate - skip'];
            $importedThisRound++;
            continue;
        }

        $post_data = array(
            'post_title'    => $data[0],
            'post_content'  => $data[2],
            'post_status'   => 'publish',
            'post_author'   => 1, // Default to the admin user
            'tags_input'    => explode(',', $data[4])
        );

        // Create the post
        $post_id = wp_insert_post($post_data);

        // Set the featured image
        if ($post_id) {
            $image_url = $data[3];
            $upload_dir = wp_upload_dir();
            $image_data = file_get_contents($image_url);
            $filename = basename($image_url);
            if (wp_mkdir_p($upload_dir['path']))
                $file = $upload_dir['path'] . '/' . $filename;
            else
                $file = $upload_dir['basedir'] . '/' . $filename;
            file_put_contents($file, $image_data);
            $wp_filetype = wp_check_filetype($filename, null);
            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => sanitize_file_name($filename),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            $attach_id = wp_insert_attachment($attachment, $file, $post_id);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $file);
            wp_update_attachment_metadata($attach_id, $attach_data);
            set_post_thumbnail($post_id, $attach_id);
        }
        $logs[] = ['title' => $data[0], 'status' => 'imported'];
        $importedThisRound++;
    }

    fclose($handle);

    echo json_encode(['importedThisRound' => $importedThisRound, 'logs' => $logs]);
    wp_die();
}

add_action('wp_ajax_upload_csv_and_count', 'upload_csv_and_count');
add_action('wp_ajax_process_articles_chunk', 'process_articles_chunk');

