# get-git-php
Webhook payload receiver using PHP and JSON. Execute commands and functions depending on the content of the payload like pull on push to a specific branch of a repo or upon push by a specific user.

## Requirements
- Access to your server
- PHP version >= 5.4

## Installation
Git clone this repo. As to how you want to run this script, you have three options: 
##### Option 1: embedded in your website as a page
You can run this as yourdomain.com/get-git-php. All that is required is that you copy index.php, rename it to something else, and paste it somewhere publicly available on your website. You can setup your webhook to send payloads there. 

##### Option 2: as a separate website
Set up a vhost in your apache configuration and run get-git as a website. Point the directory root to the webroot/ folder.  

##### Option 3: docker
Included are a sample dockerfile and docker-compose file. You would hav to mount all the directories get-git needs access to, however. Point the work directory to the webroot/ folder.

## Use

Option 1 will most likely require you to embed the behavior of the script in the index.php. If you do use the config.json file, make sure that it is outside your web directory. If the config.json file is missing, then get-git will use the embedded configuation.

The following is the sample config.json:
```
{
  "get-git-php": { \\ name of the repo
    "settings": { 
      "secret": "123450", \\ the secret you use to setup the webhook
      "ip_whitelist": [], \\ if you want to whitelist IP addresses
      "domain_whitelist": [] \\ if you want to whitelist domains
    },
    "push": [ \\ the event to trigger the comnmands here
      {
        "method": "stringIsSame", \\ function to evaluate to true or false
        "cmd": "", \\ if method is set to shell, write the shell command here
        "param": [ \\ these are the arguments passed to the method above
          "${payload.ref}", \\ the payload date is accessible via this interface. Each dot is a step down the json object. It's essentially $payload['ref']
          "refs/heads/master"
        ],
        "assert": true,  \\ when the method returns true, run the run commands
        "run": [ \\ commands to be run
          "cd /path/to/somewhere && git pull origin master"
        ]
      }
    ]
  }
}
```
The embedded config is just an array version of the above.

## Metadata

Author: hrkyoung iam@hrkyoung.com  
v 0.1.0  
MIT License