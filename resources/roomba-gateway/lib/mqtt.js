const MQTT = require("async-mqtt");

const Roomba = require('./roomba');

class Mqtt {

    constructor(connectOptions) {
        
        this.connection = null;
        this.connectOptions = connectOptions;
        this.roomba = null;
    }
    
    async init() {
        
        this.roomba = new Roomba();
        this.connection = MQTT.connect(this.connectOptions);
        
        // Register callback
        this.connection.on("connect", async () => {

            try {
            
                await this.connection.subscribe("#");
            } catch (e){
            
                process.exit();
            }
        });
        this.connection.on("message", (topic, message) => {

            if (topic == '$aws/things/' + this.connectOptions.username + '/shadow/update') {
                
                const object = JSON.parse(message.toString());
                if (typeof object === 'undefined' || typeof object.state === 'undefined' || typeof object.state.reported === 'undefined')
                    return;
        
                if (typeof object.state.reported.bin !== 'undefined') {
                    
                    this.roomba.setBin(object.state.reported.bin.present, object.state.reported.bin.full);
                }
                if (typeof object.state.reported.batPct !== 'undefined') {
                    
                    this.roomba.setBatPct(object.state.reported.batPct);
                }
                if (typeof object.state.reported.dock !== 'undefined') {
                    
                    this.roomba.setDock(object.state.reported.dock.known);
                }
                if (typeof object.state.reported.cleanMissionStatus !== 'undefined') {
                    
                    this.roomba.setCleanMissionStatus(object.state.reported.cleanMissionStatus.phase, object.state.reported.cleanMissionStatus.cycle);
                }
                if (typeof object.state.reported.bbmssn !== 'undefined') {
                    
                    this.roomba.setBbmssn(object.state.reported.bbmssn.nMssn, object.state.reported.bbmssn.nMssnOk);
                }
                if (typeof object.state.reported.bbchg3 !== 'undefined') {
                    
                    this.roomba.setBbchg3(object.state.reported.bbchg3.hOnDock);
                }
                if (typeof object.state.reported.bbrun !== 'undefined') {
                    
                    this.roomba.setBbrun(object.state.reported.bbrun.hr, object.state.reported.bbrun.nScrubs);
                }
                if (typeof object.state.reported.bbsys !== 'undefined') {
                    
                    this.roomba.setBbsys(object.state.reported.bbsys.hr);
                }
            }   
        });
    }
    
    getRoombaInfo() {
        
        return this.roomba.getInfo();
    }
    
    async disconnect() {
        
        try {
            
            await this.connection.end();
            console.log("MQTT disconnected");
        } catch (e) {
            
            console.log(e.stack);
        }
    }
}

module.exports = Mqtt;