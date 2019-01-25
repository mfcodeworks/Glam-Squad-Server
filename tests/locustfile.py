from locust import HttpLocust, TaskSet
import string, random

def registerClient(l):
    username = ''.join(random.choice(string.ascii_uppercase + string.digits) for _ in range(8))
    l.client.post("/api/v1/clients", {"username": username, "password": "password", "email": username+"@nygmarosebeauty.com"})

def registerArtist(l):
    username = ''.join(random.choice(string.ascii_uppercase + string.digits) for _ in range(8))
    l.client.post("/api/v1/artists", {
    "country": "SG",
    "username": username,
    "password": "password",
    "email": username+"@nygmarosebeauty.com",
    "bio": "Glam Squad Locust IO User",
    "instagram": "locust",
    "facebook": "locust",
    "twitter": "locust",
    "role": 1,
    "portfolio": [
        "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII="
    ]
    })

def loginClient(l):
    username = ''.join(random.choice(string.ascii_uppercase + string.digits) for _ in range(8))
    l.client.post("/api/v1/clients/authenticate", {"username": username, "password": "password"})

def loginArtist(l):
    username = ''.join(random.choice(string.ascii_uppercase + string.digits) for _ in range(8))
    l.client.post("/api/v1/artists/authenticate", {"username": username, "password": "password"})

def getClient(l):
    id = random.choice(string.digits)
    l.client.get("/api/v1/clients/"+id)

def getArtist(l):
    id = random.choice(string.digits)
    l.client.get("/api/v1/artists/"+id)

class UserBehavior(TaskSet):
    tasks = {getClient: 3, getArtist: 4}

    def on_start(self):
        registerClient(self)
        registerArtist(self)
        loginClient(self)
        loginArtist(self)

class WebsiteUser(HttpLocust):
    task_set = UserBehavior
    min_wait = 5000
    max_wait = 15000
