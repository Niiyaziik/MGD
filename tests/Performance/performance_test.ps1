$baseUrl = "http://localhost:8000"

$routes = @(
    @{
        Name = "User main page"
        Url = "$baseUrl/"
        Method = "GET"
    },

    @{
        Name = "Public index"
        Url = "$baseUrl/index.php"
        Method = "GET"
    },

    @{
        Name = "VK config"
        Url = "$baseUrl/auth/vk/config"
        Method = "GET"
    },

    @{
        Name = "Address suggest street"
        Url = "$baseUrl/address/suggest"
        Method = "POST"
        Body = '{"query":"\u0423\u043b\u044c\u044f\u043d\u043e\u0432\u0441\u043a \u041b\u0435\u043d\u0438\u043d\u0430 10"}'
        ContentType = "application/json"
    },

    @{
        Name = "Address suggest SNT"
        Url = "$baseUrl/address/suggest"
        Method = "POST"
        Body = '{"query":"\u0423\u043b\u044c\u044f\u043d\u043e\u0432\u0441\u043a \u0421\u041d\u0422 \u0412\u043e\u0441\u0442\u043e\u043a \u0443\u0447 17"}'
        ContentType = "application/json"
    },

    @{
        Name = "Check wrong SMS code"
        Url = "$baseUrl/auth/check-code"
        Method = "POST"
        Body = '{"phone":"79176354527","code":"000000"}'
        ContentType = "application/json"
    },

    @{
        Name = "Vote without session"
        Url = "$baseUrl/vote"
        Method = "POST"
        Body = '{"candidate_id":1}'
        ContentType = "application/json"
    },

    @{
        Name = "Candidates public page"
        Url = "$baseUrl/candidate.php"
        Method = "GET"
    },

    @{
        Name = "Candidate page"
        Url = "$baseUrl/candidates.php"
        Method = "GET"
    }
)

$iterations = 50
$limitMs = 300
$results = @()

foreach ($route in $routes) {
    $times = @()
    $statusCodes = @()
    $successCount = 0
    $errorCount = 0

    Write-Host "Testing: $($route.Name)"

    for ($i = 1; $i -le $iterations; $i++) {
        $watch = [System.Diagnostics.Stopwatch]::StartNew()

        try {
            $params = @{
                Uri = $route.Url
                Method = $route.Method
                UseBasicParsing = $true
                MaximumRedirection = 0
                TimeoutSec = 10
            }

            if ($route.ContainsKey("Body")) {
                $params.Body = $route.Body
            }

            if ($route.ContainsKey("ContentType")) {
                $params.ContentType = $route.ContentType
            }

            $response = Invoke-WebRequest @params
            $statusCode = [int]$response.StatusCode
            $successCount++
        }
        catch {
            if ($_.Exception.Response -ne $null) {
                $statusCode = [int]$_.Exception.Response.StatusCode
            } else {
                $statusCode = 0
            }

            $errorCount++
        }

        $watch.Stop()
        $times += $watch.ElapsedMilliseconds
        $statusCodes += $statusCode
    }

    $avg = [Math]::Round(($times | Measure-Object -Average).Average, 2)
    $min = ($times | Measure-Object -Minimum).Minimum
    $max = ($times | Measure-Object -Maximum).Maximum
    $uniqueCodes = ($statusCodes | Sort-Object -Unique) -join ", "

    if ($avg -le $limitMs) {
        $result = "NFT-1 passed"
    } else {
        $result = "NFT-1 failed"
    }

    $results += [PSCustomObject]@{
        Route = $route.Name
        Url = $route.Url
        Requests = $iterations
        Success = $successCount
        Errors = $errorCount
        StatusCodes = $uniqueCodes
        AvgMs = $avg
        MinMs = $min
        MaxMs = $max
        Result = $result
    }
}

$results | Format-Table -AutoSize

$results | Export-Csv `
    -Path "performance_results.csv" `
    -NoTypeInformation `
    -Encoding UTF8

Write-Host "Results saved to performance_results.csv"