const express = require('express');
const npid = require('npid');
const Mqtt = require('./lib/mqtt');

let webServer;
let MqttConnection;

const args = process.argv.slice(2);
const host = args[0];
const port = args[1];
const username = args[2];
const password = args[3];

try {
    var pid = npid.create(args[4]);
    pid.removeOnExit();
} catch (err) {
    console.log(err);
    process.exit(1);
}

(async () => {
    
    var  connectOptions = {
        host: host,
        port: 8883,
        clientId: username,
        protocol: "tls",
        rejectUnauthorized: false,
        username: username,
        password: password
    };
    MqttConnection = new Mqtt(connectOptions);
    await MqttConnection.init();

    var app = express();
    
    app.get('/info', function(req, res) {

        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(MqttConnection.getRoombaInfo()));
    })
    .get('/stop', function(req, res) {
        
        process.exit(0);
    })
    .use(function(req, res, next){
        
        res.setHeader('Content-Type', 'text/plain');
        res.status(404).send('Page introuvable !');
    });
    
    webServer = app.listen(port, function () {
        
        console.log("Api started on port: " + port);
        console.log("Program started");
    })
})();

process.on("SIGINT", async () => {
    
    if (MqttConnection) {
        
        await MqttConnection.disconnect();
    }
    if (webServer) {
        
        webServer.close(() => {
            
            console.log('Http server closed.');
        });
    }
    process.removeAllListeners("SIGINT");
});