const fs = require('fs');
 fs.rmdir('stuff');
  var data= fs.readFile('readme.txt', 'utf8');
     fs.writeFile('./stuff/stuffy.txt', data);
