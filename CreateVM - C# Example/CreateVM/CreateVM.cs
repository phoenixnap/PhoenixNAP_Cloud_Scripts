using System;
using System.Collections.Generic;
using System.Net;
using System.Runtime.Serialization;
//Add Reference System.Runtime.Serialization
using System.Runtime.Serialization.Json;
using System.Text;
using System.Text.RegularExpressions;
using System.Diagnostics;
using RestSharp;


namespace CreateVM
{
    class Program
    {
        // Enter Your Organization Id
        private static uint myOrgId;

        // Enter Your Application Key
        private static string applicationKey;

        // Enter Your Shared Secret
        private static string sharedSecret;

        // Enter Your The FDQN of Your Cloud
        private static string cloudDomain;

        static void Main(string[] args)
        {
            try
            {

                myOrgId = Convert.ToUInt32(Settings.Default.organization_id);
                Debug.WriteLine(myOrgId);
                applicationKey = Settings.Default.application_key;
                sharedSecret = Settings.Default.shared_secret;
                cloudDomain = Settings.Default.cloud_domain;

                string requestType, apiCommand, credentials;


                // Get the resource URLs for all the VMs under the organization.
                requestType = "POST";
                apiCommand = "/organization/" + myOrgId + "/node/1/virtualmachine";
                Debug.WriteLine(apiCommand);
                credentials = getCredentials(requestType, apiCommand, applicationKey, sharedSecret);
                Debug.WriteLine(credentials);
                var client = new RestClient();
                client.BaseUrl = "https://admin.securedcloud.com/cloud-external-api-rest";
                var request = new RestRequest();
                request.AddParameter("Authorization", credentials, ParameterType.HttpHeader);
                request.AddParameter("Accept", "application/vnd.securedcloud.v3.0+json", ParameterType.HttpHeader);
                request.Resource = apiCommand;
                request.Method = Method.POST;
                request.OnBeforeDeserialization = resp => { resp.ContentType = "application/json"; };

                CreateVM vm = new CreateVM();
                vm.name = "TestMachine";
                vm.description = "This is a Test VM";
                vm.storageGB = 16;
                vm.memoryMB = 1024;
                vm.vCPUs = 1;
                //There is a legacy option for SATA...that I am not sure works...but all VMs are made on SAS
                vm.storageType = "SAS";
                vm.powerStatus = "POWERED_OFF";
                vm.operatingSystemTemplate = new OperatingSystemTemplate();
                //You have to itterate through the available OS templates to find the one you want...
                vm.operatingSystemTemplate.resourceURL = "/ostemplate/42";
                vm.newOperatingSystemAdminPassword = "Test1234";

                var jsonCreateVMString = request.JsonSerializer.Serialize(vm);
                Debug.Write("JSON form of CreateVM object: ");
                Debug.WriteLine(jsonCreateVMString);

                request.AddParameter("application/vnd.securedcloud.v3.0+json", jsonCreateVMString, ParameterType.RequestBody);
                IRestResponse<ResourceURLList> response = client.Execute<ResourceURLList>(request);
                foreach (ResourceURL r in response.Data)
                {
                    // This shows the returned task resourceURL in the debug window
                    Debug.WriteLine(r.resourceURL);
                }
            }
            catch (Exception ex)
            {
                Debug.WriteLine(ex.Message);
            }
        }



        // Creates the Authentication string needed in the header of the API Request
        static string getCredentials(string requestType, string apiCommand, string appKey, string secret)
        {
            string stringToSign;
            string signature;
            string encodedCredentials;
            string authorization;

            System.Security.Cryptography.HMACSHA256 shaHash = new System.Security.Cryptography.HMACSHA256();

            //Send Process - Step 1
            stringToSign = requestType + " " + apiCommand + " " + appKey;

            //Send Process - Step 2
            shaHash.Key = System.Text.Encoding.ASCII.GetBytes(secret);
            signature = System.Convert.ToBase64String(shaHash.ComputeHash(System.Text.Encoding.ASCII.GetBytes(stringToSign)));

            //Send Process - Step 3
            encodedCredentials = System.Convert.ToBase64String(System.Text.Encoding.ASCII.GetBytes(appKey + ":" + signature));

            //Send Process - Step 4
            authorization = "SC " + encodedCredentials;

            return authorization;
        }
    }

    public class ResourceURLList : List<ResourceURL>
    {

    }

    public class ResourceURL 
    {
        public string resourceURL { get; set; }
    }


    [DataContract]
    public class CreateVM
    {
        [DataMember(Name = "name")]
        public string name { get; set; }
        [DataMember(Name = "description")]
        public string description { get; set; }
        [DataMember(Name = "storageGB")]
        public int storageGB { get; set; }
        [DataMember(Name = "memoryMB")]
        public int memoryMB { get; set; }
        [DataMember(Name = "vCPUs")]
        public int vCPUs { get; set; }
        [DataMember(Name = "storageType")]
        public string storageType { get; set; }
        [DataMember(Name = "powerStatus")]
        public string powerStatus { get; set; }
        [DataMember(Name = "operatingSystemTemplate")]
        public OperatingSystemTemplate operatingSystemTemplate { get; set; }
        [DataMember(Name = "newOperatingSystemAdminPassword")]
        public string newOperatingSystemAdminPassword { get; set; }
    }

    public class OperatingSystemTemplate
    {
        [DataMember(Name = "resourceURL")]
        public string resourceURL { get; set; }
    }
}