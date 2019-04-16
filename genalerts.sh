alerts1='[
  {
    "id": "12313423",
    "labels": {
       "alertname": "DNVRCO.CMFL.ARST.PortChannel2.ifStatus/HS/Err",
       "devicename": "DNVRCO.CMFL.ARST",
       "severity": "CRIT"
     },
     "annotations": {
        "info": "ifStatus/HS/Err for DNVRCO.CMFL.ARST.PortChannel2 is now CRIT",
        "summary": "blah blah blah"
      }
  },
  {
    "id": "1231342323",
    "labels": {
       "alertname": "DNVRCO.CMFL.ARST.Et1.ifStatus/HS/Err",
       "devicename": "DNVRCO.CMFL.ARST",
       "severity": "UNKNOWN"
     },
     "annotations": {
        "info": "ifStatus/HS/Err for DNVRCO.CMFL.ARST.Et1 is now UNKNOWN",
        "summary": "blah blah blah"
      }
  },
  {
    "id": "12313421233",
    "labels": {
       "alertname": "DNVRCO.CMFL.ARST.Et2.ifStatus/HS/Err",
       "devicename": "DNVRCO.CMFL.ARST",
       "severity": "UNKNOWN"
     },
     "annotations": {
        "info": "ifStatus/HS/Err for DNVRCO.CMFL.ARST.Et2 is now UNKNOWN",
        "summary": "blah blah blah"
      }
  },
  {
    "id": "12123313423",
    "labels": {
       "alertname": "DNVRCO.CMFL.ARST.PortChannel1.ifStatus/HS/Err",
       "devicename": "DNVRCO.CMFL.ARST",
       "severity": "CRIT"
     },
     "annotations": {
        "info": "ifStatus/HS/Err for DNVRCO.CMFL.ARST.PortChannel1 is now CRIT",
        "summary": "blah blah blah"
      }
  }
]'
curl -X POST -H "Content-Type: application/json" -d "$alerts1" http://localhost:8000
