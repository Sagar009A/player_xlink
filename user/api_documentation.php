<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: ../login.php');
    exit;
}

include 'header.php';
?>

<style>
.api-endpoint {
    background: #f8f9fa;
    border-left: 4px solid #007bff;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
}
.method-badge {
    font-weight: bold;
    padding: 5px 10px;
    border-radius: 3px;
    margin-right: 10px;
}
.method-GET { background: #28a745; color: white; }
.method-POST { background: #007bff; color: white; }
.method-PUT { background: #ffc107; color: black; }
.method-DELETE { background: #dc3545; color: white; }
.code-block {
    background: #272822;
    color: #f8f8f2;
    padding: 15px;
    border-radius: 5px;
    overflow-x: auto;
    font-family: 'Courier New', monospace;
}
.try-it-btn {
    margin-top: 10px;
}
.response-preview {
    background: #e9ecef;
    padding: 10px;
    border-radius: 5px;
    margin-top: 10px;
}
</style>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-code"></i> API Documentation</h1>
            </div>

            <!-- API Key Section -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-key"></i> Your API Credentials</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <label class="form-label"><strong>API Key:</strong></label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="apiKey" value="<?= $user['api_key'] ?>" readonly>
                                <button class="btn btn-outline-secondary" onclick="copyApiKey()">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                            <small class="text-muted">Keep this key secure. Do not share it publicly.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><strong>Base URL:</strong></label>
                            <code><?= SITE_URL ?>/api/</code>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Start -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-rocket"></i> Quick Start</h5>
                </div>
                <div class="card-body">
                    <h6>Authentication</h6>
                    <p>All API requests must include your API key in the request:</p>
                    <div class="code-block">
// Method 1: Query Parameter<br>
GET <?= SITE_URL ?>/api/stats.php?api_key=YOUR_API_KEY<br><br>
// Method 2: Header<br>
GET <?= SITE_URL ?>/api/stats.php<br>
Headers: X-API-Key: YOUR_API_KEY
                    </div>
                </div>
            </div>

            <!-- API Endpoints -->
            <h3 class="mb-3"><i class="fas fa-plug"></i> Available Endpoints</h3>

            <!-- 1. Create Short Link -->
            <div class="api-endpoint">
                <h5>
                    <span class="method-badge method-POST">POST</span>
                    /api/shorten.php
                </h5>
                <p>Create a new short link from a video URL</p>
                
                <h6>Parameters:</h6>
                <table class="table table-sm">
                    <tr>
                        <th>Parameter</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Description</th>
                    </tr>
                    <tr>
                        <td><code>api_key</code></td>
                        <td>string</td>
                        <td>Yes</td>
                        <td>Your API key</td>
                    </tr>
                    <tr>
                        <td><code>url</code></td>
                        <td>string</td>
                        <td>Yes</td>
                        <td>Video URL to shorten</td>
                    </tr>
                    <tr>
                        <td><code>title</code></td>
                        <td>string</td>
                        <td>No</td>
                        <td>Custom title for the link</td>
                    </tr>
                    <tr>
                        <td><code>custom_alias</code></td>
                        <td>string</td>
                        <td>No</td>
                        <td>Custom alias (if available)</td>
                    </tr>
                </table>

                <h6>Example Request:</h6>
                <div class="code-block">
curl -X POST <?= SITE_URL ?>/api/shorten.php \<br>
&nbsp;&nbsp;-H "Content-Type: application/json" \<br>
&nbsp;&nbsp;-d '{<br>
&nbsp;&nbsp;&nbsp;&nbsp;"api_key": "YOUR_API_KEY",<br>
&nbsp;&nbsp;&nbsp;&nbsp;"url": "https://example.com/video.mp4",<br>
&nbsp;&nbsp;&nbsp;&nbsp;"title": "My Awesome Video"<br>
&nbsp;&nbsp;}'
                </div>

                <h6>Example Response:</h6>
                <div class="code-block">
{<br>
&nbsp;&nbsp;"success": true,<br>
&nbsp;&nbsp;"short_code": "abc123def",<br>
&nbsp;&nbsp;"short_url": "<?= SITE_URL ?>/abc123def",<br>
&nbsp;&nbsp;"title": "My Awesome Video",<br>
&nbsp;&nbsp;"created_at": "2024-01-01 12:00:00"<br>
}
                </div>

                <button class="btn btn-primary try-it-btn" onclick="tryEndpoint('shorten')">
                    <i class="fas fa-play"></i> Try It
                </button>
                <div id="response-shorten" class="response-preview" style="display:none;"></div>
            </div>

            <!-- 2. Get Link Statistics -->
            <div class="api-endpoint">
                <h5>
                    <span class="method-badge method-GET">GET</span>
                    /api/stats.php
                </h5>
                <p>Get statistics for a specific link</p>
                
                <h6>Parameters:</h6>
                <table class="table table-sm">
                    <tr>
                        <th>Parameter</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Description</th>
                    </tr>
                    <tr>
                        <td><code>api_key</code></td>
                        <td>string</td>
                        <td>Yes</td>
                        <td>Your API key</td>
                    </tr>
                    <tr>
                        <td><code>short_code</code></td>
                        <td>string</td>
                        <td>Yes</td>
                        <td>Short code of the link</td>
                    </tr>
                </table>

                <h6>Example Request:</h6>
                <div class="code-block">
curl -X GET "<?= SITE_URL ?>/api/stats.php?api_key=YOUR_API_KEY&short_code=abc123def"
                </div>

                <h6>Example Response:</h6>
                <div class="code-block">
{<br>
&nbsp;&nbsp;"success": true,<br>
&nbsp;&nbsp;"data": {<br>
&nbsp;&nbsp;&nbsp;&nbsp;"short_code": "abc123def",<br>
&nbsp;&nbsp;&nbsp;&nbsp;"title": "My Video",<br>
&nbsp;&nbsp;&nbsp;&nbsp;"views": 1234,<br>
&nbsp;&nbsp;&nbsp;&nbsp;"earnings": 5.67,<br>
&nbsp;&nbsp;&nbsp;&nbsp;"today_views": 45,<br>
&nbsp;&nbsp;&nbsp;&nbsp;"created_at": "2024-01-01",<br>
&nbsp;&nbsp;&nbsp;&nbsp;"last_view_at": "2024-01-10"<br>
&nbsp;&nbsp;}<br>
}
                </div>

                <button class="btn btn-primary try-it-btn" onclick="tryEndpoint('stats')">
                    <i class="fas fa-play"></i> Try It
                </button>
                <div id="response-stats" class="response-preview" style="display:none;"></div>
            </div>

            <!-- 3. Get All Links -->
            <div class="api-endpoint">
                <h5>
                    <span class="method-badge method-GET">GET</span>
                    /api/links.php
                </h5>
                <p>Get all your links with pagination</p>
                
                <h6>Parameters:</h6>
                <table class="table table-sm">
                    <tr>
                        <th>Parameter</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Description</th>
                    </tr>
                    <tr>
                        <td><code>api_key</code></td>
                        <td>string</td>
                        <td>Yes</td>
                        <td>Your API key</td>
                    </tr>
                    <tr>
                        <td><code>page</code></td>
                        <td>int</td>
                        <td>No</td>
                        <td>Page number (default: 1)</td>
                    </tr>
                    <tr>
                        <td><code>limit</code></td>
                        <td>int</td>
                        <td>No</td>
                        <td>Results per page (default: 20, max: 100)</td>
                    </tr>
                </table>

                <button class="btn btn-primary try-it-btn" onclick="tryEndpoint('links')">
                    <i class="fas fa-play"></i> Try It
                </button>
                <div id="response-links" class="response-preview" style="display:none;"></div>
            </div>

            <!-- 4. Track Link -->
            <div class="api-endpoint">
                <h5>
                    <span class="method-badge method-GET">GET</span>
                    /api/track_api.php
                </h5>
                <p>Track link by short code and get details</p>
                
                <h6>Parameters:</h6>
                <table class="table table-sm">
                    <tr>
                        <th>Parameter</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Description</th>
                    </tr>
                    <tr>
                        <td><code>api_key</code></td>
                        <td>string</td>
                        <td>Yes</td>
                        <td>Your API key</td>
                    </tr>
                    <tr>
                        <td><code>action</code></td>
                        <td>string</td>
                        <td>Yes</td>
                        <td>Action type: "track" or "stats"</td>
                    </tr>
                    <tr>
                        <td><code>short_code</code></td>
                        <td>string</td>
                        <td>Yes</td>
                        <td>Short code to track</td>
                    </tr>
                </table>

                <button class="btn btn-primary try-it-btn" onclick="tryEndpoint('track')">
                    <i class="fas fa-play"></i> Try It
                </button>
                <div id="response-track" class="response-preview" style="display:none;"></div>
            </div>

            <!-- 5. Bulk Convert -->
            <div class="api-endpoint">
                <h5>
                    <span class="method-badge method-POST">POST</span>
                    /api/bulk_converter.php
                </h5>
                <p>Convert multiple URLs at once</p>
                
                <h6>Parameters:</h6>
                <table class="table table-sm">
                    <tr>
                        <th>Parameter</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Description</th>
                    </tr>
                    <tr>
                        <td><code>api_key</code></td>
                        <td>string</td>
                        <td>Yes</td>
                        <td>Your API key</td>
                    </tr>
                    <tr>
                        <td><code>urls</code></td>
                        <td>array</td>
                        <td>Yes</td>
                        <td>Array of video URLs</td>
                    </tr>
                </table>

                <h6>Example Request:</h6>
                <div class="code-block">
curl -X POST <?= SITE_URL ?>/api/bulk_converter.php \<br>
&nbsp;&nbsp;-H "Content-Type: application/json" \<br>
&nbsp;&nbsp;-d '{<br>
&nbsp;&nbsp;&nbsp;&nbsp;"api_key": "YOUR_API_KEY",<br>
&nbsp;&nbsp;&nbsp;&nbsp;"urls": [<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"https://video1.com/file.mp4",<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"https://video2.com/file.mp4"<br>
&nbsp;&nbsp;&nbsp;&nbsp;]<br>
&nbsp;&nbsp;}'
                </div>
            </div>

            <!-- Rate Limits -->
            <div class="card mb-4">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="fas fa-tachometer-alt"></i> Rate Limits</h5>
                </div>
                <div class="card-body">
                    <ul>
                        <li><strong>Per Minute:</strong> 60 requests</li>
                        <li><strong>Per Hour:</strong> 1000 requests</li>
                        <li><strong>Per Day:</strong> 10,000 requests</li>
                    </ul>
                    <p class="mb-0 text-muted">
                        <i class="fas fa-info-circle"></i> 
                        If you exceed these limits, you'll receive a 429 (Too Many Requests) response.
                    </p>
                </div>
            </div>

            <!-- Error Codes -->
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Error Codes</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>400</code></td>
                                <td>Bad Request - Missing or invalid parameters</td>
                            </tr>
                            <tr>
                                <td><code>401</code></td>
                                <td>Unauthorized - Invalid API key</td>
                            </tr>
                            <tr>
                                <td><code>404</code></td>
                                <td>Not Found - Resource doesn't exist</td>
                            </tr>
                            <tr>
                                <td><code>429</code></td>
                                <td>Too Many Requests - Rate limit exceeded</td>
                            </tr>
                            <tr>
                                <td><code>500</code></td>
                                <td>Internal Server Error</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SDKs & Libraries -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-code-branch"></i> Code Examples</h5>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#php-example">PHP</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#python-example">Python</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#javascript-example">JavaScript</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#curl-example">cURL</a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div id="php-example" class="tab-pane fade show active">
                            <div class="code-block">
&lt;?php<br>
$apiKey = '<?= $user['api_key'] ?>';<br>
$url = '<?= SITE_URL ?>/api/shorten.php';<br><br>
$data = [<br>
&nbsp;&nbsp;'api_key' => $apiKey,<br>
&nbsp;&nbsp;'url' => 'https://example.com/video.mp4',<br>
&nbsp;&nbsp;'title' => 'My Video'<br>
];<br><br>
$ch = curl_init($url);<br>
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);<br>
curl_setopt($ch, CURLOPT_POST, true);<br>
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));<br>
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);<br><br>
$response = curl_exec($ch);<br>
curl_close($ch);<br><br>
$result = json_decode($response, true);<br>
echo $result['short_url'];<br>
?&gt;
                            </div>
                        </div>

                        <div id="python-example" class="tab-pane fade">
                            <div class="code-block">
import requests<br>
import json<br><br>
api_key = '<?= $user['api_key'] ?>'<br>
url = '<?= SITE_URL ?>/api/shorten.php'<br><br>
data = {<br>
&nbsp;&nbsp;'api_key': api_key,<br>
&nbsp;&nbsp;'url': 'https://example.com/video.mp4',<br>
&nbsp;&nbsp;'title': 'My Video'<br>
}<br><br>
response = requests.post(url, json=data)<br>
result = response.json()<br>
print(result['short_url'])
                            </div>
                        </div>

                        <div id="javascript-example" class="tab-pane fade">
                            <div class="code-block">
const apiKey = '<?= $user['api_key'] ?>';<br>
const url = '<?= SITE_URL ?>/api/shorten.php';<br><br>
const data = {<br>
&nbsp;&nbsp;api_key: apiKey,<br>
&nbsp;&nbsp;url: 'https://example.com/video.mp4',<br>
&nbsp;&nbsp;title: 'My Video'<br>
};<br><br>
fetch(url, {<br>
&nbsp;&nbsp;method: 'POST',<br>
&nbsp;&nbsp;headers: { 'Content-Type': 'application/json' },<br>
&nbsp;&nbsp;body: JSON.stringify(data)<br>
})<br>
.then(response => response.json())<br>
.then(result => console.log(result.short_url));
                            </div>
                        </div>

                        <div id="curl-example" class="tab-pane fade">
                            <div class="code-block">
curl -X POST <?= SITE_URL ?>/api/shorten.php \<br>
&nbsp;&nbsp;-H "Content-Type: application/json" \<br>
&nbsp;&nbsp;-d '{<br>
&nbsp;&nbsp;&nbsp;&nbsp;"api_key": "<?= $user['api_key'] ?>",<br>
&nbsp;&nbsp;&nbsp;&nbsp;"url": "https://example.com/video.mp4",<br>
&nbsp;&nbsp;&nbsp;&nbsp;"title": "My Video"<br>
&nbsp;&nbsp;}'
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
function copyApiKey() {
    const apiKey = document.getElementById('apiKey');
    apiKey.select();
    document.execCommand('copy');
    alert('API Key copied to clipboard!');
}

function tryEndpoint(endpoint) {
    const apiKey = '<?= $user['api_key'] ?>';
    const responseDiv = document.getElementById('response-' + endpoint);
    responseDiv.style.display = 'block';
    responseDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing endpoint...';
    
    let url = '';
    let method = 'GET';
    let data = null;
    
    switch(endpoint) {
        case 'shorten':
            url = '<?= SITE_URL ?>/api/shorten.php';
            method = 'POST';
            data = JSON.stringify({
                api_key: apiKey,
                url: 'https://example.com/test-video.mp4',
                title: 'Test Video from API Docs'
            });
            break;
        case 'stats':
            url = '<?= SITE_URL ?>/api/stats.php?api_key=' + apiKey + '&short_code=test';
            break;
        case 'links':
            url = '<?= SITE_URL ?>/api/links.php?api_key=' + apiKey + '&limit=5';
            break;
        case 'track':
            url = '<?= SITE_URL ?>/api/track_api.php?api_key=' + apiKey + '&action=track&short_code=test';
            break;
    }
    
    fetch(url, {
        method: method,
        headers: method === 'POST' ? { 'Content-Type': 'application/json' } : {},
        body: data
    })
    .then(response => response.json())
    .then(data => {
        responseDiv.innerHTML = '<strong>Response:</strong><pre>' + JSON.stringify(data, null, 2) + '</pre>';
    })
    .catch(error => {
        responseDiv.innerHTML = '<strong class="text-danger">Error:</strong> ' + error.message;
    });
}
</script>

<?php include 'footer.php'; ?>
