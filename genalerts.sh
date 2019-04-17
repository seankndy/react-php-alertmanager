alerts1='[
  {
    "id": "12313423",
    "expiryDuration": "60",
    "attributes": {
       "service_id": "10",
       "port_id": "132",
       "location_name": "DNVRCO.CMFL",
       "device_name": "ARST",
       "severity": "CRIT",
       "info": "ifStatus/HS/Err for DNVRCO.CMFL.ARST.PortChannel2 is now CRIT",
       "summary": "blah blah blah"
    }
  },
  {
    "id": "1231342323",
    "expiryDuration": "60",
    "attributes": {
      "service_id": "11",
      "port_id": "18",
      "location_name": "CLIENTS.BLACKFOOT",
      "device_name": "RB2011",
      "severity": "CRIT",
      "info": "ifStatus/HS/Err for CLIENTS.BLACKFOOT.RB2011 is now CRIT",
      "summary": "blah blah blah"
    }
  }
]'
curl -X POST -H "Content-Type: application/json" -d "$alerts1" http://localhost:8000
