
class Roomba {

    constructor() {
        
        this.bin = null;
        this.batPct = null;
        this.dock = null;
        this.cleanMissionStatus = null;
        this.bbmssn = null;
        this.bbchg3 = null;
        this.bbrun = null;
        this.bbsys = null;
    }
    
    setBin(present, full) {
        
        this.bin = {
            present: present,
            full: full
        }
    }
    
    getBin() {
        
        return this.bin;
    }
    
    setBatPct(batPct) {
        
        this.batPct = batPct;
    }
    
    getBatPct() {
        
        return this.batPct;
    }

    setDock(known) {
        
        this.dock = {
            known: known
        }
    }
    
    getDock() {
        
        return this.dock;
    }
    
    setCleanMissionStatus(phase, cycle) {
        
        this.cleanMissionStatus = {
            phase: phase,
            cycle: cycle
        }
    }
    
    getCleanMissionStatus() {
        
        return this.cleanMissionStatus;
    }
    
    setBbmssn(nMssn, nMssnOk) {
        
        this.bbmssn = {
            nMssn: nMssn,
            nMssnOk: nMssnOk
        }
    }
    
    getBbmssn() {
        
        return this.bbmssn;
    }
    
    setBbchg3(hOnDock) {
        
        this.bbchg3 = {
            hOnDock: hOnDock
        }
    }
    
    getBbchg3() {
        
        return this.bbchg3;
    }
    
    setBbrun(hr, nScrubs) {
        
        this.bbrun = {
            hr: hr,
            nScrubs: nScrubs
        }
    }
    
    getBbrun() {
        
        return this.bbrun;
    }
    
    setBbsys(hr) {
        
        this.bbsys = {
            hr: hr
        }
    }
    
    getBbsys() {
        
        return this.bbsys;
    }
    
    getInfo() {
        
        return {
            bin: this.getBin(),
            batPct: this.getBatPct(),
            dock: this.getDock(),
            cleanMissionStatus: this.getCleanMissionStatus(),
            bbmssn: this.getBbmssn(),
            bbchg3: this.getBbchg3(),
            bbrun: this.getBbrun(),
            bbsys: this.getBbsys(),
        };
    }
}

module.exports = Roomba;