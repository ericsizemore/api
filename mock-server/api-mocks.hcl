# Simple 404
mock "GET /404" {
    status = 404

    headers {
        Content-Type = "application/json"
    }
}

# Rate limit exceeded, with delay
mock "GET /429" {
    status = 429

    headers {
        Content-Type = "application/json"
        Retry-After  = "2"
    }
}

# Internal error, with delay
mock "GET /500" {
    status = 500

    headers {
        Content-Type = "application/json"
        Retry-After  = "2"
    }
}

# Internal error, no delay
mock "GET /500/nodelay" {
    status = 500

    headers {
        Content-Type = "application/json"
    }
}

#
mock "GET /anything" {
    status = 200

    headers {
        Content-Type  = "application/json"
    }

    body = <<EOF
    {
        "args": {},
        "headers": {
            "Accept": [
                "application/json"
            ],
            "Client-Id": [
                "apiKey"
            ],
            "Authorization": [
                "someAccessToken"
            ]
        }
    }
    EOF
}

#
mock "GET /get/full" {
    status = 200

    headers {
        Content-Type  = "application/json"
    }

    body = <<EOF
    {
        "args": {
            "foo":["bar"]
        },
        "headers": {
            "Accept": [
                "application/json"
            ],
            "Client-Id": [
                "apiKey"
            ],
            "Authorization": [
                "someAccessToken"
            ]
        },
        "origin": "127.0.0.1",
        "url": "http://localhost:8080/get/full?foo=bar"
    }
    EOF
}

#
mock "GET /anything/query1" {
    status = 200

    headers {
        Content-Type  = "application/json"
    }

    body = <<EOF
    {
        "args": {
            "foo":["bar"]
        },
        "headers": {
            "Accept": [
                "application/json"
            ],
            "Client-Id": [
                "apiKey"
            ],
            "Authorization": [
                "someAccessToken"
            ]
        }
    }
    EOF
}

#
mock "GET /anything/query2" {
    status = 200

    headers {
        Content-Type  = "application/json"
    }

    body = <<EOF
    {
        "args": {
            "foo":["bar"]
        },
        "headers": {
            "Accept": [
                "application/json"
            ],
            "Client-Id": [
                "anotherApiKey"
            ],
            "Authorization": [
                "someOtherAccessToken"
            ]
        }
    }
    EOF
}

# Standard get, with query params including api key
mock "GET /get/withkey" {
    status = 200

    headers {
        Content-Type = "application/json"
    }

    body = <<EOF
    {
        "args": {
            "api_key":["test"],
            "foo":["bar"]
        }
    }
    EOF
}

# Standard get, with query params
mock "GET /get" {
    status = 200

    headers {
        Content-Type = "application/json"
    }

    body = <<EOF
    {
        "args": {
            "foo":["bar"]
        }
    }
    EOF
}

# Standard get, without query params
mock "GET /get/noquery" {
    status = 200

    headers {
        Content-Type = "application/json"
    }

    body = <<EOF
    {
        "args": {}
    }
    EOF
}
