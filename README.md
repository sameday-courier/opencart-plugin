### Sameday Plugin for Opencart 2.3.* and 3.*
<hr> 
<strong> Overview </strong>

<p> 
        This plug-in is intended to implement a new shipping method using the Sameday Courier service.  As a store owner, 
    after installing the plugin, you are able to import the list of Sameday Courier service and delivery points assigned 
    to your account.   If your customer chooses the order to be delivered with Sameday Courier, you will be able 
    to see this in the list of commands in your store's administration panel.  You will also be able to create an AWB. 
    You can then add a new parcel in the created AWB and show the AWB as a pdf format.   
    If you want, you can show the AWB history or delete the AWB.
</p>
 <hr> 
<strong> Account & Pricing: </strong>

<p> 
        Using this plug-in is free. However, the service offered by our company is based on a contract. 
    The terms and conditions of the contract are negotiated individually. After signing the contract, the client will 
    receive a set of credentials (username and password). With these credentials, the customer will be able to use 
    the Sameday Courier delivery service. 
</p>

If your are facing some issues when working with our solution our you want to leave us a feedback, please don't hesitate to contact us at plugineasybox@sameday.ro !
 <hr> 
<strong> Features: </strong> 

<ul>
    <li> Config Sameday Courier shipping method </li>  
</ul>
<ul>
    <li> Import Sameday Courier pickup-points </li>  
</ul>
<ul>
    <li> Import Sameday Courier services </li>  
</ul>
<ul>
    <li> Show AWB as PDF format </li>  
</ul>
<ul>
    <li> Add new parcel in AWB </li>  
</ul>
<ul>
    <li> Show AWB status and summary </li>  
</ul>
 <hr> 

### Code Style
This project use [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) and has their own ruleset
defined at `phpcs.xml.dist`

- In order to start verification, run:
  php vendor/bin/phpcs --standard=phpcs.xml.dist

- In order to automatically fix wherever is possible, run :
  php vendor/bin/phpcbf --standard=phpcs.xml.dist

### CI/CD
This project uses Azure Pipelines for continuous integration:

- **SonarQube Analysis**: Automatic code quality checks on every pull request
- **Security Scanning**: Automated security vulnerability detection

Pull requests will automatically trigger the CI pipeline for code quality verification.